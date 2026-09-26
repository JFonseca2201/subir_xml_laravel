<?php

namespace App\Models\WorkOrder;

use App\Models\Finance\Account;
use App\Models\Finance\FinanceRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\SerializeDateLocal;

class WorkOrderAdvance extends Model
{
    use SoftDeletes, SerializeDateLocal;

    protected $table = 'work_order_advances';

    protected $fillable = [
        'work_order_id',
        'account_id',
        'finance_record_id',
        'amount',
        'payment_method',
        'advance_date',
        'receipt_number',
        'notes',
        'user_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'advance_date' => 'date:Y-m-d',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    public function workOrder()
    {
        return $this->belongsTo(WorkOrder::class, 'work_order_id');
    }

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function financeRecord()
    {
        return $this->belongsTo(FinanceRecord::class, 'finance_record_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
