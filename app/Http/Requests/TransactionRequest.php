<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TransactionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'type' => 'required|in:cheque,debit',
            'document.id' => 'required|exists:documents,id',
            'document.capex_no' => 'required_if:document.id,5',
            'document.name' => 'required|exists:documents,type',
            'document.payment_type' => 'required|in:full,partial',
            'document.no' => 'required_if:document.id,1,2',
            'document.date' => 'required_if:document.id,1,2,5',
            'document.amount' => 'required_if:document.id,1,5,2,6,8,7|numeric',
            'document.remarks' => 'nullable',
            'document.company.id' => 'required|exists:companies,id',
            'document.company.name' => 'required|exists:companies,company',
            'document.department.id' => 'required|exists:departments,id',
            'document.department.name' => 'required|exists:departments,department',
            'document.location.id' => 'required|exists:locations,id',
            'document.location.name' => 'required|exists:locations,location',
            'document.supplier.id' => 'required|exists:suppliers,id',
            'document.supplier.name' => 'required|exists:suppliers,name',
            'document.from' => 'required_if:document.id,6,7',
            'document.to' => 'required_if:document.id,6,7',
            'document.category.id' => 'required_if:document.id,1,2,4,5',
            'document.category.name' => 'required_if:document.id,1,2,4,5',
            'po_group' => 'nullable',
            'po_group.*.no' => 'nullable',
            'po_group.*.amount' => 'nullable|numeric',
            'po_group.*.rr_no' => 'nullable',
            "purchase_order.*.rr_id" => "nullable",
            "purchase_order.*.rr_number" => "nullable",
            "purchase_order.*.rr_orders" => "nullable",
            "purchase_order.*.rr_orders.*.item_code" => "nullable",
            "purchase_order.*.rr_orders.*.item_name" => "nullable",
            "purchase_order.*.rr_orders.*.price" => "nullable",
            "purchase_order.*.rr_orders.*.reference_no" => "nullable",
            "purchase_order.*.rr_orders.*.quantity_receive" => "nullable",
            "purchase_order.*.rr_orders.*.uom_code" => "nullable",
            "purchase_order.*.rr_orders.*.uom_name" => "nullable",
            "purchase_order.*.purchase_orders.*.po_number" => "nullable",
            "purchase_order.*.purchase_orders.*.po_amount" => "nullable",
            "purchase_order.*.purchase_orders.*.consumed_amount" => "nullable",
            "purchase_order.*.purchase_orders.*.remaining_amount" => "nullable",
            "purchase_order.*.purchase_orders.*.po_description" => "nullable",
            "purchase_order.*.purchase_orders.*.type_name" => "nullable",
            "purchase_order.*.purchase_orders.*.company.id" => "nullable",
            "purchase_order.*.purchase_orders.*.company.code" => "nullable",
            "purchase_order.*.purchase_orders.*.company.name" => "nullable",
            "purchase_order.*.purchase_orders.*.business_unit.id" => "nullable",
            "purchase_order.*.purchase_orders.*.business_unit.code" => "nullable",
            "purchase_order.*.purchase_orders.*.business_unit.name" => "nullable",
            "purchase_order.*.purchase_orders.*.department.id" => "nullable",
            "purchase_order.*.purchase_orders.*.department.code" => "nullable",
            "purchase_order.*.purchase_orders.*.department.name" => "nullable",
            "purchase_order.*.purchase_orders.*.unit.id" => "nullable",
            "purchase_order.*.purchase_orders.*.unit.code" => "nullable",
            "purchase_order.*.purchase_orders.*.unit.name" => "nullable",
            "purchase_order.*.purchase_orders.*.sub_unit.id" => "nullable",
            "purchase_order.*.purchase_orders.*.sub_unit.code" => "nullable",
            "purchase_order.*.purchase_orders.*.sub_unit.name" => "nullable",
            "purchase_order.*.purchase_orders.*.location.id" => "nullable",
            "purchase_order.*.purchase_orders.*.location.code" => "nullable",
            "purchase_order.*.purchase_orders.*.location.name" => "nullable",
            "purchase_order.*.purchase_orders.*.account_title.id" => "nullable",
            "purchase_order.*.purchase_orders.*.account_title.code" => "nullable",
            "purchase_order.*.purchase_orders.*.account_title.name" => "nullable",
            'document.utility.receipt_no' => 'required_if:document.id,6',
            "document.utility.consumption" => "nullable",
            "document.utility.location.id" => "required_if:document.id,6",
            "document.utility.location.name" => "required_if:document.id,6",
            "document.utility.category.id" => "required_if:document.id,6",
            "document.utility.category.name" => "required_if:document.id,6",
            "document.utility.account_no.id" => "required_if:document.id,6",
            "document.utility.account_no.no" => "required_if:document.id,6",
            "document.pcf_batch.name" => "required_if:document.id,8",
            "document.pcf_batch.letter" => "required_if:document.id,8",
            "document.pcf_batch.date" => "required_if:document.id,8",
            "document.payroll.clients.*.id" => "required_if:document.id,7",
            "document.payroll.clients.*.name" => "required_if:document.id,7",
            "document.payroll.type" => "required_if:document.id,7",
            "document.payroll.category.id" => "required_if:document.id,7",
            "document.payroll.category.name" => "required_if:document.id,7",
            "document.payroll.control_no" => 'nullable',
            "document.reference.id" => "required_if:document.id,4",
            "document.reference.no" => "required_if:document.id,4",
            "document.reference.amount" => "nullable",
            "document.reference.allowable" => "nullable",
            "document.reference.qty" => "nullable",
            "document.reference.type" => "required_if:document.id,4",

            "prm_group.*.period_covered" => "nullable",
            "prm_group.*.gross_amount" => "nullable",
            "prm_group.*.wht" => "nullable",
            "prm_group.*.net_of_amount" => "nullable",
            "prm_group.*.cheque_date" => "nullable",
            "prm_group.*.amortization" => "nullable",
            "prm_group.*.interest" => "nullable",
            "prm_group.*.cwt" => "nullable",
            "prm_group.*.principal" => "nullable",
            "document.batch_no" => "nullable",
            "document.release_date" => "nullable",
            "autoDebit_group.*.pn_no" => "nullable",
            "autoDebit_group.*.interest_from" => "nullable",
            "autoDebit_group.*.interest_to" => "nullable",
            "autoDebit_group.*.outstanding_amount" => "nullable",
            "autoDebit_group.*.interest_rate" => "nullable",
            "autoDebit_group.*.no_of_days" => "nullable",
            "autoDebit_group.*.principal_amount" => "nullable",
            "autoDebit_group.*.interest_due" => "nullable",
            "autoDebit_group.*.cwt" => "nullable",
            "autoDebit_group.*.dst" => "nullable",
            "service_group.*.company" => 'nullable',
            "service_group.*.business_unit" => 'nullable',
            "service_group.*.department" => 'nullable',
            "service_group.*.sub_unit" => 'nullable',
            "service_group.*.location" => 'nullable',
            "service_group.*.amount" => 'nullable',
            "document.business_unit.id" => 'nullable',
            "document.business_unit.name" => 'nullable',
            "document.sub_unit.id" => 'nullable',
            "document.sub_unit.name" => 'nullable',
            "po_balance" => 'nullable'
        ];
    }
}
