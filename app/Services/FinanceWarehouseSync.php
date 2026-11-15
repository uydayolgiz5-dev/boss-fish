<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Farm;
use App\Models\User;
use App\Models\WarehouseItem;
use App\Support\FinanceCategories;
use Illuminate\Validation\ValidationException;

/**
 * Moliyadan yem/dori/uskuna yaratilmaydi — faqat Ombor → Kirim.
 * Bu klass faqat (eski) bog‘liq yozuvni reverse qilish uchun qolgan.
 */
class FinanceWarehouseSync
{
    public function apply(Expense $expense, Farm $farm, User $user): void
    {
        if (FinanceCategories::originatesFromWarehouse((string) $expense->category)) {
            throw ValidationException::withMessages([
                'category' => ['Yem, dori va uskuna Ombor → Kirim orqali kiritiladi. Moliyadan kiritilmaydi.'],
            ]);
        }
    }

    public function reverse(Expense $expense): void
    {
        if (! $expense->warehouse_item_id) {
            return;
        }

        $quantity = (float) ($expense->quantity ?? 0);
        if ($quantity <= 0) {
            return;
        }

        $item = WarehouseItem::query()->find($expense->warehouse_item_id);
        if (! $item) {
            return;
        }

        $newQuantity = round((float) $item->quantity - $quantity, 2);
        if ($newQuantity < 0) {
            throw ValidationException::withMessages([
                'warehouse' => ['Bu xarajat ombordan allaqachon ishlatilgan. Avval ombordagi qoldiqni tekshiring.'],
            ]);
        }

        $reverseAmount = (float) ($expense->amount ?? 0);
        if ($reverseAmount <= 0 && $expense->unit_price !== null) {
            $reverseAmount = round($quantity * (float) $expense->unit_price, 2);
        }

        $item->quantity = $newQuantity;
        if ($reverseAmount > 0 && $item->amount !== null) {
            $item->amount = max(0, round((float) $item->amount - $reverseAmount, 2));
        }
        if ($newQuantity <= 0) {
            $item->unit_price = null;
            $item->amount = null;
        }
        $item->save();
    }

    public function resync(Expense $expense, Farm $farm, User $user, Expense $before): void
    {
        // Ombor kategoriyasiga o‘tkazish yoki ombor yozuvini moliyadan qayta sync qilish yo‘q
        if (FinanceCategories::originatesFromWarehouse((string) $expense->category)) {
            throw ValidationException::withMessages([
                'category' => ['Yem, dori va uskuna Ombor → Kirim orqali kiritiladi. Moliyadan kiritilmaydi.'],
            ]);
        }

        if (
            FinanceCategories::originatesFromWarehouse((string) $before->category)
            && $before->warehouse_item_id
        ) {
            throw ValidationException::withMessages([
                'category' => ['Bu xarajat Ombordan kelgan. O‘zgartirish uchun Ombor bo‘limidan foydalaning.'],
            ]);
        }
    }
}
