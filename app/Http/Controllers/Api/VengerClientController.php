<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VengerClient;
use App\Models\VengerResult;
use App\Support\Districts;
use App\Support\FishTypes;
use App\Support\Regions;
use App\Support\VengerFeedTypes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class VengerClientController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $region = trim((string) $request->query('region', ''));

        $query = VengerClient::query()
            ->withCount('results')
            ->with([
                'creator:id,name',
                'results' => fn ($rel) => $rel->latest('sold_on')->limit(1),
            ])
            ->latest();

        $this->scopeToStaff($query, $request);

        if ($region !== '') {
            $query->where('region', $region);
        }

        if ($q !== '') {
            $like = '%'.$q.'%';
            $digits = preg_replace('/\D+/', '', $q) ?: null;
            $query->where(function ($inner) use ($like, $digits) {
                $inner->where('name', 'like', $like)
                    ->orWhere('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('phone2', 'like', $like)
                    ->orWhere('region', 'like', $like)
                    ->orWhere('district', 'like', $like);
                if ($digits) {
                    $inner->orWhere('phone', 'like', '%'.$digits.'%')
                        ->orWhere('phone2', 'like', '%'.$digits.'%');
                }
            });
        }

        $clients = $query->get()->map(fn (VengerClient $c) => $this->clientListPayload($c));

        $clientsTotal = VengerClient::query();
        $this->scopeToStaff($clientsTotal, $request);
        $resultsTotal = VengerResult::query();
        $this->scopeToStaff($resultsTotal, $request);

        return response()->json([
            'clients' => $clients,
            'stats' => [
                'clients_count' => (int) $clientsTotal->count(),
                'results_count' => (int) $resultsTotal->count(),
                'filtered_count' => $clients->count(),
            ],
        ]);
    }

    public function listPonds(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        $base = VengerResult::query()->with(['client', 'fish', 'creator:id,name']);
        $this->scopeToStaff($base, $request);

        if ($q !== '') {
            $like = '%'.$q.'%';
            $digits = preg_replace('/\D+/', '', $q) ?: null;
            $base->where(function ($inner) use ($like, $digits) {
                $inner->where('title', 'like', $like)
                    ->orWhereHas('client', function ($clientQ) use ($like, $digits) {
                        $clientQ->where('name', 'like', $like)
                            ->orWhere('first_name', 'like', $like)
                            ->orWhere('last_name', 'like', $like)
                            ->orWhere('phone', 'like', $like)
                            ->orWhere('region', 'like', $like)
                            ->orWhere('district', 'like', $like);
                        if ($digits) {
                            $clientQ->orWhere('phone', 'like', '%'.$digits.'%');
                        }
                    });
            });
        }

        // Jarayonda: eng eski / yangilanganlari tepada (davom ettirish uchun)
        $open = (clone $base)
            ->where('status', VengerResult::STATUS_ACTIVE)
            ->orderBy('updated_at')
            ->get()
            ->map(fn (VengerResult $r) => $this->pondListPayload($r))
            ->values();

        // Yakunlangan: alohida arxiv — oxirgi yakunlanganlar tepada
        $final = (clone $base)
            ->where('status', VengerResult::STATUS_FINAL)
            ->orderByDesc('sold_on')
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (VengerResult $r) => $this->pondListPayload($r))
            ->values();

        return response()->json([
            'open' => $open,
            'final' => $final,
            // eski clientlar uchun
            'ponds' => $open,
            'stats' => [
                'open' => $open->count(),
                'final' => $final->count(),
                'total' => $open->count() + $final->count(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateClient($request);

        $client = VengerClient::create([
            ...$data,
            'name' => VengerClient::fullName($data['first_name'], $data['last_name']),
            'created_by' => $request->user()->id,
        ]);

        return response()->json($this->clientDetailPayload($client->loadCount('results')), 201);
    }

    public function show(Request $request, VengerClient $client): JsonResponse
    {
        $this->ensureStaffOwnsClient($request, $client);
        $client->load(['results' => fn ($q) => $q->latest('sold_on'), 'creator:id,name']);

        return response()->json($this->clientDetailPayload($client, true));
    }

    public function update(Request $request, VengerClient $client): JsonResponse
    {
        $this->ensureStaffOwnsClient($request, $client);
        $data = $this->validateClient($request, $client->id);
        $client->update([
            ...$data,
            'name' => VengerClient::fullName($data['first_name'], $data['last_name']),
        ]);

        return response()->json($this->clientDetailPayload($client->fresh()->loadCount('results')));
    }

    public function destroy(Request $request, VengerClient $client): JsonResponse
    {
        $this->ensureStaffOwnsClient($request, $client);
        $client->delete();

        return response()->json(['ok' => true]);
    }

    public function storeResult(Request $request, VengerClient $client): JsonResponse
    {
        $this->ensureStaffOwnsClient($request, $client);
        [$meta, $fishRows] = $this->validateResult($request);

        $result = DB::transaction(function () use ($client, $request, $meta, $fishRows) {
            $result = $client->results()->create([
                ...$meta,
                'stocked_on' => $fishRows[0]['stocked_on'],
                'sold_on' => $fishRows[0]['sold_on'] ?? $fishRows[0]['stocked_on'],
                'stocked_pieces' => 0,
                'stocked_weight_kg' => 0,
                'sold_pieces' => 0,
                'sold_weight_kg' => 0,
                'created_by' => $request->user()->id,
            ]);

            $this->syncFishRows($result, $fishRows);

            return $result;
        });

        return response()->json($this->resultPayload($result->load(['client', 'fish', 'creator:id,name'])), 201);
    }

    public function showResult(Request $request, VengerResult $result): JsonResponse
    {
        $this->ensureStaffOwnsResult($request, $result);
        $result->load(['client', 'creator:id,name', 'fish']);

        return response()->json($this->resultPayload($result));
    }

    public function updateResult(Request $request, VengerResult $result): JsonResponse
    {
        $this->ensureStaffOwnsResult($request, $result);
        [$meta, $fishRows] = $this->validateResult($request);

        DB::transaction(function () use ($result, $meta, $fishRows) {
            $result->update($meta);
            $this->syncFishRows($result, $fishRows);
        });

        return response()->json($this->resultPayload($result->fresh()->load(['client', 'creator:id,name', 'fish'])));
    }

    public function destroyResult(Request $request, VengerResult $result): JsonResponse
    {
        $this->ensureStaffOwnsResult($request, $result);
        $result->delete();

        return response()->json(['ok' => true]);
    }

    /** Superadmin (egasi) — hammasi; Venger hodim — faqat o‘z yozuvlari. */
    private function scopeToStaff($query, Request $request): void
    {
        $user = $request->user();
        if ($user && $user->isSuperadminStaff() && ! $user->isSuperadmin()) {
            $query->where('created_by', $user->id);
        }
    }

    private function ensureStaffOwnsClient(Request $request, VengerClient $client): void
    {
        $user = $request->user();
        if ($user && $user->isSuperadminStaff() && ! $user->isSuperadmin() && (int) $client->created_by !== (int) $user->id) {
            abort(404);
        }
    }

    private function ensureStaffOwnsResult(Request $request, VengerResult $result): void
    {
        $user = $request->user();
        if ($user && $user->isSuperadminStaff() && ! $user->isSuperadmin() && (int) $result->created_by !== (int) $user->id) {
            abort(404);
        }
    }

    private function validateClient(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'first_name' => 'required|string|max:120',
            'last_name' => 'required|string|max:120',
            'address' => 'nullable|string|max:500',
            'region' => ['required', 'string', Rule::in(Regions::all())],
            'district' => 'required|string|max:120',
            'pond_area_ha' => 'required|numeric|min:0.001|max:999999',
            'phone' => [
                'required',
                'string',
                'max:32',
                'regex:/^\+998\d{9}$/',
                Rule::unique('venger_clients', 'phone')->ignore($ignoreId),
            ],
            'phone2' => [
                'nullable',
                'string',
                'max:32',
                'regex:/^\+998\d{9}$/',
                'different:phone',
                Rule::unique('venger_clients', 'phone')->ignore($ignoreId),
                Rule::unique('venger_clients', 'phone2')->ignore($ignoreId),
            ],
            'notes' => 'nullable|string|max:2000',
        ]);

        if (! Districts::isValid($data['region'], $data['district'])) {
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
     * @return array{0: array, 1: list<array>}
     */
    private function validateResult(Request $request): array
    {
        $data = $request->validate([
            'title' => 'nullable|string|max:255',
            'region' => ['required', 'string', Rule::in(Regions::all())],
            'district' => 'required|string|max:120',
            'address' => 'nullable|string|max:500',
            'area_ha' => 'required|numeric|min:0.001|max:999999',
            'feed_kg' => 'nullable|numeric|min:0',
            'feeds' => 'nullable|array',
            'feeds.*.type' => ['required_with:feeds', 'string', Rule::in(VengerFeedTypes::accepted())],
            'feeds.*.kg' => 'nullable|numeric|min:0',
            'controls' => 'nullable|array',
            'controls.*.checked_on' => 'nullable|date',
            'controls.*.avg_g' => 'nullable|numeric|min:0',
            'controls.*.feed_kg' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:5000',
            'status' => ['nullable', 'string', Rule::in([VengerResult::STATUS_ACTIVE, VengerResult::STATUS_FINAL])],
            'fish' => 'required|array|min:1',
            'fish.*.fish_type' => ['required', 'string', Rule::in(FishTypes::all())],
            'fish.*.stocked_on' => 'required|date',
            'fish.*.stocked_pieces' => 'nullable|integer|min:0',
            'fish.*.stocked_weight_kg' => 'required|numeric|min:0.01',
            'fish.*.current_on' => 'nullable|date|after_or_equal:fish.*.stocked_on',
            'fish.*.current_pieces' => 'nullable|integer|min:0',
            'fish.*.current_weight_kg' => 'nullable|numeric|min:0',
            'fish.*.sold_on' => 'nullable|date|after_or_equal:fish.*.stocked_on',
            'fish.*.sold_pieces' => 'nullable|integer|min:0',
            'fish.*.sold_weight_kg' => 'nullable|numeric|min:0',
            'fish.*.loss_on' => 'nullable|date|after_or_equal:fish.*.stocked_on',
            'fish.*.loss_pieces' => 'nullable|integer|min:0',
            'fish.*.loss_weight_kg' => 'nullable|numeric|min:0',
            'fish.*.notes' => 'nullable|string|max:2000',
        ]);

        if (! Districts::isValid($data['region'], $data['district'])) {
            throw ValidationException::withMessages([
                'district' => ['Tanlangan tuman/shahar ushbu viloyatga tegishli emas.'],
            ]);
        }

        $types = collect($data['fish'])->pluck('fish_type');
        if ($types->count() !== $types->unique()->count()) {
            throw ValidationException::withMessages([
                'fish' => ['Har bir baliq turi faqat bir marta kiritiladi.'],
            ]);
        }

        $fishRows = [];
        $hasSold = false;
        foreach ($data['fish'] as $index => $row) {
            $soldPieces = (int) ($row['sold_pieces'] ?? 0);
            $lossPieces = (int) ($row['loss_pieces'] ?? 0);
            $stockedPieces = (int) ($row['stocked_pieces'] ?? 0);
            $soldWeight = (float) ($row['sold_weight_kg'] ?? 0);
            $lossWeight = (float) ($row['loss_weight_kg'] ?? 0);
            $currentWeight = isset($row['current_weight_kg']) && $row['current_weight_kg'] !== ''
                ? (float) $row['current_weight_kg']
                : null;

            if ($soldWeight > 0) {
                $hasSold = true;
            }

            if ($soldWeight > 0 && empty($row['sold_on'])) {
                throw ValidationException::withMessages([
                    "fish.$index.sold_on" => ['Sotuv sanasi majburiy.'],
                ]);
            }

            if ($currentWeight !== null && $currentWeight > 0 && empty($row['current_on'])) {
                throw ValidationException::withMessages([
                    "fish.$index.current_on" => ['Oraliq o‘lchov sanasi majburiy.'],
                ]);
            }

            $fishRows[] = [
                'fish_type' => $row['fish_type'],
                'stocked_on' => $row['stocked_on'],
                'stocked_pieces' => $stockedPieces,
                'stocked_weight_kg' => (float) $row['stocked_weight_kg'],
                'current_on' => $row['current_on'] ?? null,
                'current_pieces' => (int) ($row['current_pieces'] ?? 0) ?: null,
                'current_weight_kg' => $currentWeight,
                'sold_on' => $row['sold_on'] ?? null,
                'sold_pieces' => $soldPieces,
                'sold_weight_kg' => $soldWeight,
                'loss_on' => $row['loss_on'] ?? null,
                'loss_pieces' => $lossPieces,
                'loss_weight_kg' => $lossWeight,
                'notes' => $row['notes'] ?? null,
            ];
        }

        $status = $data['status'] ?? VengerResult::STATUS_ACTIVE;
        if ($status === VengerResult::STATUS_FINAL && ! $hasSold) {
            throw ValidationException::withMessages([
                'status' => ['Yakunlash uchun kamida bitta turda sotuv massasini yozing.'],
            ]);
        }
        if ($hasSold && ($data['status'] ?? null) === null) {
            $status = VengerResult::STATUS_FINAL;
        }

        $feeds = [];
        $seenFeedTypes = [];
        foreach ($data['feeds'] ?? [] as $index => $row) {
            $type = VengerFeedTypes::normalize($row['type'] ?? null);
            if (! $type) {
                throw ValidationException::withMessages([
                    "feeds.$index.type" => ['Yem turi Ekstruder yoki Granula bo‘lishi kerak.'],
                ]);
            }
            if (isset($seenFeedTypes[$type])) {
                continue;
            }
            $kg = round((float) ($row['kg'] ?? 0), 2);
            if ($kg <= 0) {
                continue;
            }
            $seenFeedTypes[$type] = true;
            $feeds[] = [
                'type' => $type,
                'kg' => $kg,
                'label' => VengerFeedTypes::label($type),
            ];
        }

        $controls = [];
        foreach ($data['controls'] ?? [] as $row) {
            if (empty($row['checked_on']) || (float) ($row['avg_g'] ?? 0) <= 0) {
                continue;
            }
            $controls[] = [
                'checked_on' => $row['checked_on'],
                'avg_g' => round((float) $row['avg_g'], 1),
                'feed_kg' => round((float) ($row['feed_kg'] ?? 0), 2),
            ];
        }
        usort($controls, fn ($a, $b) => strcmp($a['checked_on'], $b['checked_on']));

        $feedsTotal = round(collect($feeds)->sum('kg'), 2);
        $requestFeedKg = (float) ($data['feed_kg'] ?? 0);

        // Yem faqat yuqoridagi turlar (yoki umumiy feed_kg) — kontrol yemi hisobga olinmaydi
        $feedKg = $feedsTotal > 0 ? $feedsTotal : $requestFeedKg;

        // Eng so‘nggi kontrolni sazan current_* ga yozib qo‘yamiz (oraliq natija).
        // Sotuv bor bo‘lsa — yakuniy massa sotuvdan, kontrol faqat tarix.
        if (! empty($controls) && ! $hasSold) {
            $latest = end($controls);
            foreach ($fishRows as $i => $fishRow) {
                if (($fishRow['fish_type'] ?? '') !== 'sazan') {
                    continue;
                }
                $pieces = max((int) ($fishRow['stocked_pieces'] ?? 0), 0);
                $estKg = $pieces > 0 ? round($pieces * ((float) $latest['avg_g'] / 1000), 2) : null;
                $fishRows[$i]['current_on'] = $latest['checked_on'];
                $fishRows[$i]['current_pieces'] = $pieces ?: null;
                $fishRows[$i]['current_weight_kg'] = $estKg;
            }
        }

        $meta = [
            'title' => $data['title'] ?? null,
            'region' => $data['region'],
            'district' => $data['district'],
            'address' => $data['address'] ?? null,
            'area_ha' => (float) $data['area_ha'],
            'feed_kg' => $feedKg,
            'feeds' => $feeds ?: null,
            'controls' => $controls ?: null,
            'notes' => $data['notes'] ?? null,
            'status' => $status,
        ];

        return [$meta, $fishRows];
    }

    private function syncFishRows(VengerResult $result, array $fishRows): void
    {
        $result->fish()->delete();

        foreach ($fishRows as $row) {
            $result->fish()->create($row);
        }

        $result->unsetRelation('fish');
        $result->syncAggregatesFromFish();
    }

    private function pondListPayload(VengerResult $result): array
    {
        $metrics = $result->metrics();
        $phase = $metrics['phase'] ?? 'stocked';
        $workflow = $result->status === VengerResult::STATUS_FINAL
            ? 'final'
            : ($phase === 'mid' ? 'mid' : 'started');

        $result->loadMissing('creator:id,name');

        return [
            'id' => $result->id,
            'title' => $result->title,
            'status' => $result->status,
            'workflow' => $workflow,
            'phase' => $phase,
            'area_ha' => $result->area_ha,
            'region' => $result->region,
            'district' => $result->district,
            'address' => $result->address,
            'stocked_on' => optional($result->stocked_on)?->toDateString(),
            'sold_on' => optional($result->sold_on)?->toDateString(),
            'feed_kg' => $result->feed_kg,
            'feeds' => $result->feeds ?? [],
            'client' => $result->client ? [
                'id' => $result->client->id,
                'name' => $result->client->name,
                'region' => $result->client->region,
                'district' => $result->client->district,
                'phone' => $result->client->phone,
            ] : null,
            'staff' => $result->creator ? [
                'id' => $result->creator->id,
                'name' => $result->creator->name,
            ] : null,
            'metrics' => [
                'fcr' => $metrics['fcr'] ?? null,
                'phase' => $phase,
                'sazan' => $metrics['sazan'] ?? null,
            ],
            'updated_at' => optional($result->updated_at)?->toIso8601String(),
        ];
    }

    private function clientListPayload(VengerClient $client): array
    {
        $latest = $client->results->first();

        return [
            'id' => $client->id,
            'name' => $client->name,
            'first_name' => $client->first_name,
            'last_name' => $client->last_name,
            'address' => $client->address,
            'region' => $client->region,
            'district' => $client->district,
            'pond_area_ha' => $client->pond_area_ha !== null ? round((float) $client->pond_area_ha, 3) : null,
            'phone' => $client->phone,
            'phone2' => $client->phone2,
            'notes' => $client->notes,
            'results_count' => (int) ($client->results_count ?? $client->results()->count()),
            'latest_result' => $latest ? [
                'id' => $latest->id,
                'sold_on' => optional($latest->sold_on)?->toDateString(),
                'area_ha' => $latest->area_ha,
                'fcr' => $latest->metrics()['fcr'],
            ] : null,
            'staff' => $client->creator ? [
                'id' => $client->creator->id,
                'name' => $client->creator->name,
            ] : null,
            'created_at' => optional($client->created_at)?->toIso8601String(),
        ];
    }

    private function clientDetailPayload(VengerClient $client, bool $withResults = false): array
    {
        $payload = [
            'id' => $client->id,
            'name' => $client->name,
            'first_name' => $client->first_name,
            'last_name' => $client->last_name,
            'address' => $client->address,
            'region' => $client->region,
            'district' => $client->district,
            'pond_area_ha' => $client->pond_area_ha !== null ? round((float) $client->pond_area_ha, 3) : null,
            'phone' => $client->phone,
            'phone2' => $client->phone2,
            'notes' => $client->notes,
            'results_count' => (int) ($client->results_count ?? $client->results()->count()),
            'created_by' => $client->creator?->only(['id', 'name']),
            'created_at' => optional($client->created_at)?->toIso8601String(),
        ];

        if ($withResults) {
            $client->loadMissing(['results.fish', 'feedPlanRows.plan']);

            $planByYear = [];
            foreach ($client->feedPlanRows as $row) {
                $year = (int) ($row->plan?->year ?? 0);
                if ($year <= 0) {
                    continue;
                }
                $extruder = (float) ($row->next_year_extruder_kg ?? 0);
                $granula = (float) ($row->next_year_granule_kg ?? 0);
                $planByYear[$year] = [
                    'year' => $year,
                    'pond_area_ha' => $row->pond_area_ha !== null ? round((float) $row->pond_area_ha, 3) : null,
                    'fish_stocked' => (int) ($row->next_year_carp_count ?? 0),
                    'plan_extruder_kg' => round($extruder, 1),
                    'plan_granula_kg' => round($granula, 1),
                    'plan_feed_kg' => round($extruder + $granula, 1),
                ];
            }

            $results = $client->results->map(function (VengerResult $r) use ($planByYear) {
                $stockedOn = optional($r->stocked_on)?->toDateString();
                $soldOn = optional($r->sold_on)?->toDateString();
                $season = $this->seasonLabel($stockedOn, $soldOn);
                $startYear = $stockedOn ? (int) date('Y', strtotime($stockedOn)) : null;
                $plan = null;
                if ($startYear) {
                    $plan = $planByYear[$startYear + 1] ?? $planByYear[$startYear] ?? null;
                }

                $metrics = $r->metrics();
                $feeds = collect($r->feeds ?? []);
                $extruderKg = (float) $feeds
                    ->filter(fn ($f) => \App\Support\VengerFeedTypes::normalize($f['type'] ?? null) === \App\Support\VengerFeedTypes::EXTRUDER)
                    ->sum(fn ($f) => (float) ($f['kg'] ?? 0));
                $granulaKg = (float) $feeds
                    ->filter(fn ($f) => \App\Support\VengerFeedTypes::normalize($f['type'] ?? null) === \App\Support\VengerFeedTypes::GRANULA)
                    ->sum(fn ($f) => (float) ($f['kg'] ?? 0));
                $resultFeedKg = ($extruderKg + $granulaKg) > 0
                    ? round($extruderKg + $granulaKg, 1)
                    : round((float) ($r->feed_kg ?? 0), 1);

                return [
                    'id' => $r->id,
                    'title' => $r->title,
                    'status' => $r->status,
                    'area_ha' => $r->area_ha,
                    'region' => $r->region,
                    'district' => $r->district,
                    'address' => $r->address,
                    'stocked_on' => $stockedOn,
                    'sold_on' => $soldOn,
                    'season' => $season,
                    'stocked_pieces' => $r->stocked_pieces,
                    'sold_pieces' => $r->sold_pieces,
                    'feed_kg' => $resultFeedKg,
                    'result_extruder_kg' => round($extruderKg, 1),
                    'result_granula_kg' => round($granulaKg, 1),
                    'result_feed_kg' => $resultFeedKg,
                    'plan' => $plan,
                    'feeds' => $r->feeds ?? [],
                    'sold_weight_kg' => $r->sold_weight_kg,
                    'fish_types' => $r->fish->pluck('fish_type')->values(),
                    'metrics' => $metrics,
                ];
            })->values();

            $payload['results'] = $results;
            $payload['feed_plans'] = array_values($planByYear);

            $fcrValues = $results
                ->map(fn ($r) => $r['metrics']['fcr'] ?? null)
                ->filter(fn ($v) => $v !== null)
                ->values();
            $payload['summary'] = [
                'results_count' => $results->count(),
                'pond_area_ha' => $client->pond_area_ha !== null ? round((float) $client->pond_area_ha, 3) : null,
                'result_feed_kg' => round((float) $results->sum('result_feed_kg'), 1),
                'plan_feed_kg' => round((float) collect($planByYear)->sum('plan_feed_kg'), 1),
                'avg_fcr' => $fcrValues->isEmpty()
                    ? null
                    : round((float) $fcrValues->avg(), 2),
                'weight_gain_kg' => round((float) $results->sum(function ($r) {
                    return (float) ($r['metrics']['sazan']['biomass_gain_kg']
                        ?? $r['metrics']['weight_gain_kg']
                        ?? 0);
                }), 1),
            ];
        }

        return $payload;
    }

    private function resultPayload(VengerResult $result): array
    {
        $result->loadMissing(['fish', 'client', 'creator:id,name']);
        $metrics = $result->metrics();

        return [
            'id' => $result->id,
            'title' => $result->title,
            'status' => $result->status,
            'area_ha' => $result->area_ha,
            'region' => $result->region,
            'district' => $result->district,
            'address' => $result->address,
            'stocked_on' => $metrics['stocked_on'] ?? optional($result->stocked_on)?->toDateString(),
            'stocked_pieces' => $metrics['stocked_pieces'] ?? $result->stocked_pieces,
            'stocked_weight_kg' => $metrics['stocked_weight_kg'] ?? $result->stocked_weight_kg,
            'feed_kg' => $result->feed_kg,
            'feeds' => collect($result->feeds ?? [])
                ->map(function ($row) {
                    $type = VengerFeedTypes::normalize($row['type'] ?? null);

                    return $type ? [
                        'type' => $type,
                        'kg' => isset($row['kg']) ? (float) $row['kg'] : 0,
                        'label' => VengerFeedTypes::label($type),
                    ] : null;
                })
                ->filter()
                ->unique('type')
                ->values()
                ->all(),
            'controls' => $metrics['controls'] ?? $result->controlsList()->all(),
            'sold_on' => $metrics['sold_on'] ?? optional($result->sold_on)?->toDateString(),
            'sold_pieces' => $metrics['sold_pieces'] ?? $result->sold_pieces,
            'sold_weight_kg' => $metrics['sold_weight_kg'] ?? $result->sold_weight_kg,
            'loss_pieces' => $metrics['loss_pieces'] ?? 0,
            'loss_weight_kg' => $metrics['loss_weight_kg'] ?? 0,
            'notes' => $result->notes,
            'fish' => $metrics['fish'] ?? [],
            'created_by' => $result->creator?->only(['id', 'name']),
            'client' => $result->client ? [
                'id' => $result->client->id,
                'name' => $result->client->name,
                'first_name' => $result->client->first_name,
                'last_name' => $result->client->last_name,
                'address' => $result->client->address,
                'region' => $result->client->region,
                'district' => $result->client->district,
                'pond_area_ha' => $result->client->pond_area_ha !== null
                    ? round((float) $result->client->pond_area_ha, 3)
                    : null,
                'phone' => $result->client->phone,
                'phone2' => $result->client->phone2,
            ] : null,
            'season' => $this->seasonLabel(
                $metrics['stocked_on'] ?? optional($result->stocked_on)?->toDateString(),
                $metrics['sold_on'] ?? optional($result->sold_on)?->toDateString(),
            ),
            'metrics' => $metrics,
            'created_at' => optional($result->created_at)?->toIso8601String(),
            'updated_at' => optional($result->updated_at)?->toIso8601String(),
        ];
    }

    private function seasonLabel(?string $stockedOn, ?string $soldOn): ?string
    {
        $yearOf = static function (?string $value): ?int {
            if (! $value) {
                return null;
            }
            $ts = strtotime($value);

            return $ts ? (int) date('Y', $ts) : null;
        };

        $start = $yearOf($stockedOn) ?? $yearOf($soldOn);
        if (! $start) {
            return null;
        }

        // Yillik mavsum: 2026/2027
        return $start.'/'.($start + 1);
    }
}

