<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VengerClient;
use App\Models\VengerFeedPlan;
use App\Models\VengerFeedPlanRow;
use App\Support\Districts;
use App\Support\Regions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class VengerFeedPlanController extends Controller
{
    public function index(): JsonResponse
    {
        $plans = VengerFeedPlan::query()
            ->with('rows')
            ->orderBy('year')
            ->get();

        $series = $plans->map(function (VengerFeedPlan $plan) {
            $totals = $this->planTotals($plan->rows);

            return [
                'id' => $plan->id,
                'year' => $plan->year,
                'title' => $plan->title,
                'notes' => $plan->notes,
                'rows_count' => $totals['clients_count'],
                'totals' => $totals,
                'created_at' => optional($plan->created_at)?->toIso8601String(),
            ];
        })->values();

        $comparison = [];
        foreach ($series as $idx => $row) {
            $prev = $idx > 0 ? $series[$idx - 1] : null;
            $comparison[] = [
                'year' => $row['year'],
                'plan_id' => $row['id'],
                'totals' => $row['totals'],
                'growth' => [
                    'clients_count' => $this->growthPct($prev['totals']['clients_count'] ?? null, $row['totals']['clients_count']),
                    'pond_area_ha' => $this->growthPct($prev['totals']['pond_area_ha'] ?? null, $row['totals']['pond_area_ha']),
                    'last_year_feed_kg' => $this->growthPct($prev['totals']['last_year_feed_kg'] ?? null, $row['totals']['last_year_feed_kg']),
                    'next_year_carp_count' => $this->growthPct($prev['totals']['next_year_carp_count'] ?? null, $row['totals']['next_year_carp_count']),
                    'next_year_extruder_kg' => $this->growthPct($prev['totals']['next_year_extruder_kg'] ?? null, $row['totals']['next_year_extruder_kg']),
                    'next_year_granule_kg' => $this->growthPct($prev['totals']['next_year_granule_kg'] ?? null, $row['totals']['next_year_granule_kg']),
                    'total_feed_plan_t' => $this->growthPct(
                        $prev ? (($prev['totals']['next_year_extruder_kg'] ?? 0) + ($prev['totals']['next_year_granule_kg'] ?? 0)) : null,
                        ($row['totals']['next_year_extruder_kg'] ?? 0) + ($row['totals']['next_year_granule_kg'] ?? 0),
                    ),
                ],
                'vs_year' => $prev['year'] ?? null,
            ];
        }

        return response()->json([
            'plans' => $series->sortByDesc('year')->values(),
            'comparison' => array_reverse($comparison),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'year' => 'required|integer|min:2020|max:2100|unique:venger_feed_plans,year',
            'title' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:5000',
        ]);

        $plan = VengerFeedPlan::query()->create([
            'year' => (int) $data['year'],
            'title' => $data['title'] ?? null,
            'notes' => $data['notes'] ?? null,
            'created_by' => $request->user()?->id,
        ]);

        return response()->json($this->planDetailPayload($plan->fresh()->load('rows.client')), 201);
    }

    public function show(VengerFeedPlan $plan): JsonResponse
    {
        $plan->load(['rows.client:id,name,first_name,last_name,phone,region,district']);

        return response()->json($this->planDetailPayload($plan));
    }

    public function update(Request $request, VengerFeedPlan $plan): JsonResponse
    {
        $data = $request->validate([
            'title' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:5000',
        ]);

        $plan->update($data);

        return response()->json($this->planDetailPayload($plan->fresh()->load('rows.client')));
    }

    public function destroy(VengerFeedPlan $plan): JsonResponse
    {
        $plan->delete();

        return response()->json(['ok' => true]);
    }

    public function storeRow(Request $request, VengerFeedPlan $plan): JsonResponse
    {
        $data = $this->validateRow($request);
        $client = $this->resolveClient($request, $data);

        $maxSort = (int) $plan->rows()->max('sort_order');

        $row = $plan->rows()->create([
            ...$this->rowAttributes($data),
            'venger_client_id' => $client->id,
            'sort_order' => $maxSort + 1,
        ]);

        return response()->json($this->rowPayload($row->load('client')), 201);
    }

    public function updateRow(Request $request, VengerFeedPlan $plan, VengerFeedPlanRow $row): JsonResponse
    {
        $this->assertRowBelongs($plan, $row);
        $data = $this->validateRow($request, partial: true);
        $attrs = $this->rowAttributes($data);

        if (isset($data['client_name']) || isset($data['phone']) || array_key_exists('region', $data) || array_key_exists('district', $data)) {
            $merged = array_merge($row->only([
                'client_name', 'phone', 'location_text', 'region', 'district',
            ]), $attrs);
            $client = $this->resolveClient($request, $merged, $row->venger_client_id);
            $attrs['venger_client_id'] = $client->id;
        }

        $row->update($attrs);

        return response()->json($this->rowPayload($row->fresh()->load('client')));
    }

    public function destroyRow(VengerFeedPlan $plan, VengerFeedPlanRow $row): JsonResponse
    {
        $this->assertRowBelongs($plan, $row);
        $row->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateRow(Request $request, bool $partial = false): array
    {
        $req = $partial ? 'sometimes' : 'required';

        $data = $request->validate([
            'client_name' => [$req, 'string', 'max:255'],
            'phone' => [$req, 'string', 'max:32', 'regex:/^\+998\d{9}$/'],
            'phone2' => ['nullable', 'string', 'max:32', 'regex:/^\+998\d{9}$/', 'different:phone'],
            'location_text' => 'nullable|string|max:500',
            'region' => ['nullable', 'string', Rule::in(Regions::all())],
            'district' => 'nullable|string|max:120',
            'pond_area_ha' => 'nullable|numeric|min:0|max:999999',
            'last_year_feed_kg' => 'nullable|numeric|min:0|max:999999999',
            'rating_10' => 'nullable|integer|min:1|max:10',
            'feedback' => 'nullable|string|max:5000',
            'next_year_carp_count' => 'nullable|integer|min:0|max:999999999',
            'next_year_extruder_kg' => 'nullable|numeric|min:0|max:999999999',
            'next_year_granule_kg' => 'nullable|numeric|min:0|max:999999999',
            'venger_client_id' => 'nullable|integer|exists:venger_clients,id',
        ]);

        if (! empty($data['region']) && ! empty($data['district']) && ! Districts::isValid($data['region'], $data['district'])) {
            throw ValidationException::withMessages([
                'district' => ['Tanlangan tuman/shahar ushbu viloyatga tegishli emas.'],
            ]);
        }

        if (($data['phone2'] ?? null) === '') {
            $data['phone2'] = null;
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function rowAttributes(array $data): array
    {
        $keys = [
            'client_name', 'phone', 'phone2', 'location_text', 'region', 'district',
            'pond_area_ha', 'last_year_feed_kg', 'rating_10', 'feedback',
            'next_year_carp_count', 'next_year_extruder_kg', 'next_year_granule_kg',
        ];

        $out = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $data)) {
                $out[$key] = $data[$key];
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveClient(Request $request, array $data, ?int $preferredId = null): VengerClient
    {
        if (! empty($data['venger_client_id'])) {
            $client = VengerClient::query()->findOrFail((int) $data['venger_client_id']);
            $this->syncClientFromRow($client, $data);

            return $client;
        }

        if ($preferredId) {
            $preferred = VengerClient::query()->find($preferredId);
            $phone = (string) ($data['phone'] ?? '');
            if ($preferred && ($preferred->phone === $phone || $preferred->phone2 === $phone || $phone === '')) {
                $this->syncClientFromRow($preferred, $data);

                return $preferred;
            }
        }

        $phone = (string) ($data['phone'] ?? '');
        $phone2 = (string) ($data['phone2'] ?? '');
        $existing = VengerClient::query()
            ->where(function ($q) use ($phone, $phone2) {
                if ($phone !== '') {
                    $q->where('phone', $phone)->orWhere('phone2', $phone);
                }
                if ($phone2 !== '') {
                    $q->orWhere('phone', $phone2)->orWhere('phone2', $phone2);
                }
            })
            ->first();
        if ($existing) {
            $this->syncClientFromRow($existing, $data);

            return $existing;
        }

        [$first, $last] = $this->splitName((string) ($data['client_name'] ?? ''));
        $region = $data['region'] ?? null;
        $district = $data['district'] ?? null;
        if (! $region) {
            [$region, $district] = $this->guessRegionDistrict((string) ($data['location_text'] ?? ''));
        }

        return VengerClient::query()->create([
            'name' => VengerClient::fullName($first, $last),
            'first_name' => $first,
            'last_name' => $last,
            'phone' => $phone,
            'phone2' => $phone2 !== '' ? $phone2 : null,
            'address' => $data['location_text'] ?? null,
            'region' => $region,
            'district' => $district,
            'notes' => null,
            'created_by' => $request->user()?->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncClientFromRow(VengerClient $client, array $data): void
    {
        $updates = [];
        if (! empty($data['client_name'])) {
            [$first, $last] = $this->splitName((string) $data['client_name']);
            $updates['first_name'] = $first;
            $updates['last_name'] = $last;
            $updates['name'] = VengerClient::fullName($first, $last);
        }
        if (! empty($data['phone'])) {
            $updates['phone'] = $data['phone'];
        }
        if (array_key_exists('phone2', $data)) {
            $updates['phone2'] = $data['phone2'] ?: null;
        }
        if (array_key_exists('location_text', $data) && $data['location_text']) {
            $updates['address'] = $data['location_text'];
        }
        if (! empty($data['region'])) {
            $updates['region'] = $data['region'];
        }
        if (! empty($data['district'])) {
            $updates['district'] = $data['district'];
        }
        if ($updates) {
            $client->update($updates);
        }
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitName(string $full): array
    {
        $full = trim(preg_replace('/\s+/u', ' ', $full) ?: '');
        if ($full === '') {
            return ['Mijoz', ''];
        }
        $parts = explode(' ', $full, 2);

        return [$parts[0], $parts[1] ?? ''];
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function guessRegionDistrict(string $location): array
    {
        $location = trim($location);
        if ($location === '') {
            return [null, null];
        }

        foreach (Regions::all() as $region) {
            if (mb_stripos($location, $region) !== false) {
                $rest = trim(str_ireplace($region, '', $location), " \t\n\r\0\x0B,/|-");
                $districts = Districts::forRegion($region);
                foreach ($districts as $district) {
                    if ($rest !== '' && mb_stripos($rest, $district) !== false) {
                        return [$region, $district];
                    }
                }

                return [$region, $districts[0] ?? null];
            }
        }

        return [null, null];
    }

    private function assertRowBelongs(VengerFeedPlan $plan, VengerFeedPlanRow $row): void
    {
        if ((int) $row->venger_feed_plan_id !== (int) $plan->id) {
            abort(404);
        }
    }

    private function planListPayload(VengerFeedPlan $plan): array
    {
        return [
            'id' => $plan->id,
            'year' => $plan->year,
            'title' => $plan->title,
            'notes' => $plan->notes,
            'rows_count' => (int) ($plan->rows_count ?? $plan->rows()->count()),
            'created_at' => optional($plan->created_at)?->toIso8601String(),
        ];
    }

    private function planDetailPayload(VengerFeedPlan $plan): array
    {
        $rows = $plan->rows;
        $totals = $this->planTotals($rows);

        return [
            ...$this->planListPayload($plan),
            'totals' => $totals,
            'rows' => $rows->map(fn (VengerFeedPlanRow $row) => $this->rowPayload($row))->values(),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, VengerFeedPlanRow>  $rows
     * @return array{clients_count:int,pond_area_ha:float,last_year_feed_kg:float,next_year_carp_count:int,next_year_extruder_kg:float,next_year_granule_kg:float}
     */
    private function planTotals($rows): array
    {
        return [
            'clients_count' => $rows->count(),
            'pond_area_ha' => round((float) $rows->sum('pond_area_ha'), 3),
            'last_year_feed_kg' => round((float) $rows->sum('last_year_feed_kg'), 2),
            'next_year_carp_count' => (int) $rows->sum('next_year_carp_count'),
            'next_year_extruder_kg' => round((float) $rows->sum('next_year_extruder_kg'), 2),
            'next_year_granule_kg' => round((float) $rows->sum('next_year_granule_kg'), 2),
        ];
    }

    private function growthPct(mixed $prev, mixed $current): ?float
    {
        $p = $prev === null ? null : (float) $prev;
        $c = (float) $current;
        if ($p === null) {
            return null;
        }
        if ($p == 0.0) {
            return $c > 0 ? 100.0 : 0.0;
        }

        return round((($c - $p) / abs($p)) * 100, 1);
    }

    private function rowPayload(VengerFeedPlanRow $row): array
    {
        return [
            'id' => $row->id,
            'venger_feed_plan_id' => $row->venger_feed_plan_id,
            'venger_client_id' => $row->venger_client_id,
            'sort_order' => $row->sort_order,
            'client_name' => $row->client_name,
            'phone' => $row->phone,
            'phone2' => $row->phone2,
            'location_text' => $row->location_text,
            'region' => $row->region,
            'district' => $row->district,
            'pond_area_ha' => $row->pond_area_ha,
            'last_year_feed_kg' => $row->last_year_feed_kg,
            'rating_10' => $row->rating_10,
            'feedback' => $row->feedback,
            'next_year_carp_count' => $row->next_year_carp_count,
            'next_year_extruder_kg' => $row->next_year_extruder_kg,
            'next_year_granule_kg' => $row->next_year_granule_kg,
            'client' => $row->relationLoaded('client') && $row->client ? [
                'id' => $row->client->id,
                'name' => $row->client->name,
                'phone' => $row->client->phone,
                'phone2' => $row->client->phone2,
            ] : null,
        ];
    }
}
