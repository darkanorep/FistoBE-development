<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
  public function creationOfCheque(Request $request)
  {
      $from = $request->input("from", Carbon::now()->format("Y-m-d"));
      $to = $request->input("to", Carbon::now()->format("Y-m-d"));

    $page = max(1, (int) $request->input("page", 1));
    $rows = max(1, (int) $request->input("rows", 10));
    $paginate = $request->input("paginate", true);

    $result = DB::select(
      DB::raw("
        SELECT
            transactions.voucher_no,
            transactions.voucher_month,
            transactions.tag_no,
            transactions.receipt_type,
            transactions.deleted_at,
            transactions.state,
            transactions.supplier,
            e.amount AS net_amount,
            transactions.transaction_type,
            b.date_vouchered,
            f.date_transmitted,
            users.first_name,
            users.last_name
        FROM transactions
        LEFT JOIN (
            SELECT MAX(a.id) AS id, a.transaction_id, MAX(a.created_at) AS date_vouchered
            FROM associates a
            WHERE status = 'voucher-voucher'
            GROUP BY a.transaction_id
        ) b ON transactions.id = b.transaction_id
        LEFT JOIN (
            SELECT d.associate_id,
                   d.account_title_name,
                   d.amount
            FROM voucher_account_title d
            WHERE d.account_title_name LIKE '%Accounts Payable%'
        ) e ON b.id = e.associate_id

            LEFT JOIN (
            SELECT MAX(e.id) AS id, e.transaction_id, MAX(e.created_at) AS date_transmitted
            FROM transmit e
            WHERE status = 'transmit-transmit'
            GROUP BY e.transaction_id
        ) f ON transactions.id = f.transaction_id

        LEFT JOIN users ON transactions.assigned_id = users.id
        WHERE transactions.deleted_at IS NULL
          AND transactions.state != 'void'
         AND (
               (status IN ('transmit-transmit', 'audit-return', 'release-return', 'file-return') AND transactions.document_id != 8)
               OR status = 'inspect-inspect'
         )
        AND DATE_FORMAT(transactions.voucher_month, '%Y-%m-%d') BETWEEN :from AND :to
    "),
      [
        "from" => $from,
        "to" => $to,
      ]
    );

    $formattedResults = collect($result)
      ->map(function ($data) {
        return $this->reportFormatter($data);
      })
      ->values();

    if (!$paginate) {
      return $formattedResults;
    }

    return new \Illuminate\Pagination\LengthAwarePaginator(
      $formattedResults->forPage($page, $rows)->values(),
      $formattedResults->count(),
      $rows,
      $page,
      ["path" => $request->url(), "query" => $request->query()]
    );
  }

  public function corporateTransmittal(Request $request)
  {
    $from = $request->input("from", Carbon::now()->format("Y-m-d"));
    $to = $request->input("to", Carbon::now()->format("Y-m-d"));
    $page = max(1, (int) $request->input("page", 1));
    $rows = max(1, (int) $request->input("rows", 10));
    $paginate = $request->input("paginate", true);

     $result = DB::select(
      DB::raw("
        SELECT
            MAX(a.id) AS id,
            MAX(a.tag_no) AS tag_no,
            MAX(a.receipt_type) as receipt_type,
            MAX(a.supplier) AS supplier,
            MAX(a.deleted_at) AS deleted_at,
            MAX(a.voucher_no) AS voucher_no,
            MAX(a.voucher_month) AS voucher_month,
            MAX(a.status) AS status,
            c.bank_name,
            c.cheque_no,
            MAX(c.cheque_amount) AS cheque_amount,
            MAX(c.cheque_date) AS cheque_date,
            MAX(f.created_at) AS date_vouchered,
            MAX(h.date_transmitted) as date_transmitted
        FROM transactions a
        LEFT JOIN (
            SELECT
                b.transaction_id,
                b.cheque_no,
                b.bank_name,
                b.cheque_amount,
                b.cheque_date
            FROM cheques b
        ) c ON c.transaction_id = a.id
        LEFT JOIN (
            SELECT e.id, e.transaction_id, e.created_at
            FROM associates e
            WHERE status = 'voucher-voucher'
        ) f ON a.id = f.transaction_id


        LEFT JOIN (
            SELECT MAX(g.id) as id, g.transaction_id, MAX(g.created_at) AS date_transmitted
            FROM executives g
            WHERE status = 'executive-executive'
            GROUP BY g.transaction_id
        ) h ON a.id = h.transaction_id

        WHERE a.deleted_at IS NULL
          AND (
                a.status = 'executive-executive'
                OR a.status = 'issue-receive'
            )
          AND a.state != 'void'
          AND c.cheque_date IS NULL
          AND a.deleted_at IS NULL
          AND DATE_FORMAT(a.voucher_month, '%Y-%m-%d') BETWEEN :from AND :to
        GROUP BY c.cheque_no, c.bank_name
    "),
      [
        "from" => $from,
        "to" => $to,
      ]
    );

    $formattedResults = collect($result)
      ->map(function ($data) {
        return $this->reportFormatter($data);
      })
      ->values();

    if (!$paginate) {
      return $formattedResults;
    }

    return new \Illuminate\Pagination\LengthAwarePaginator(
      $formattedResults->forPage($page, $rows)->values(),
      $formattedResults->count(),
      $rows,
      $page,
      ["path" => $request->url(), "query" => $request->query()]
    );
  }

  public function treasuryReleasing(Request $request)
  {
    $from = $request->input("from", Carbon::now()->format("Y-m-d"));
    $to = $request->input("to", Carbon::now()->format("Y-m-d"));
    $page = max(1, (int) $request->input("page", 1));
    $rows = max(1, (int) $request->input("rows", 10));
    $paginate = $request->input("paginate", true);

    $result = DB::select(
      DB::raw("
                SELECT
                    MAX(a.id) AS id,
                    MAX(a.tag_no) AS tag_no,
                    MAX(a.receipt_type) as receipt_type,
                    MAX(a.supplier) AS supplier,
                    MAX(a.deleted_at) AS deleted_at,
                    MAX(a.voucher_no) AS voucher_no,
                    MAX(a.voucher_month) AS voucher_month,
                    MAX(a.status) AS status,
                    c.bank_name,
                    c.cheque_no,
                    MAX(c.cheque_amount) AS cheque_amount,
                    MAX(c.cheque_date) AS cheque_date,
                    MAX(d.created_at) AS released_date,
                    MAX(f.created_at) AS date_vouchered
                FROM transactions a
                LEFT JOIN (
                    SELECT
                        b.transaction_id,
                        b.cheque_no,
                        b.bank_name,
                        b.cheque_amount,
                        b.cheque_date,
                        b.deleted_at
                    FROM cheques b
                ) c ON c.transaction_id = a.id
                LEFT JOIN (
                    SELECT c.transaction_id, c.created_at, c.status
                    FROM issues c
                    WHERE c.status = 'issue-issue'
                      AND c.created_at = (
                          SELECT MAX(c2.created_at)
                          FROM issues c2
                          WHERE c2.transaction_id = c.transaction_id
                            AND c2.status = 'issue-issue'
                      )
                ) d ON a.id = d.transaction_id
                LEFT JOIN (
                    SELECT e.id, e.transaction_id, e.created_at
                    FROM associates e
                    WHERE status = 'voucher-voucher'
                ) f ON a.id = f.transaction_id
                WHERE a.deleted_at IS NULL
                AND (
                       a.status = 'issue-issue'
                       OR a.status = 'release-receive'
                  )
                  AND a.state != 'void'
                  AND c.cheque_date IS NOT NULL
                  AND c.deleted_at IS NULL
                  AND a.deleted_at IS NULL
                  AND DATE_FORMAT(a.voucher_month, '%Y-%m-%d') BETWEEN :from AND :to
                GROUP BY c.cheque_no, c.bank_name
            "),
      [
        "from" => $from,
        "to" => $to,
      ]
    );

    $formattedResults = collect($result)
      ->map(function ($data) {
        return $this->reportFormatter($data);
      })
      ->values();

    if (!$paginate) {
      return $formattedResults;
    }

    return new \Illuminate\Pagination\LengthAwarePaginator(
      $formattedResults->forPage($page, $rows)->values(),
      $formattedResults->count(),
      $rows,
      $page,
      ["path" => $request->url(), "query" => $request->query()]
    );
  }

  public function chequeCreated(Request $request) {
      $from = $request->input("from", Carbon::now()->format("Y-m-d"));
      $to = $request->input("to", Carbon::now()->format("Y-m-d"));
      $page = max(1, (int) $request->input("page", 1));
      $rows = max(1, (int) $request->input("rows", 10));
      $paginate = $request->input("paginate", true);

      $transactions = Transaction::
      with([
          'company_info:id,code',
          'chequeHistory',
          'treasuryCheque',
          'company_info',
          'cheques' => function ($query) {
              $query->with([
                  'account_title'
              ]);
          }
      ])
          ->whereHas('chequeHistory', function ($query) use ($from, $to) {
              $query->whereBetween('created_at', [$from, $to]);
          })
          ->select(
              'id',
              'tag_no',
              'company_id',
              'company',
              'supplier',
              'remarks',
              'voucher_no',
              'document_date',
              'pcf_date'
          )
          ->get();

      $formattedResults = $transactions->flatMap(function ($item) {
          $datetime = Carbon::parse($this->getDateEveryStatus($item->cheques, 'cheque-receive'))->setTimezone('Asia/Manila');

          // If no cheques, return empty array
          if ($item->treasuryCheque->isEmpty()) {
              return [];
          }

          // Return one item per cheque
          return $item->treasuryCheque->map(function ($cheque) use ($item, $datetime) {
              return [
                  'date_received' => $datetime->format('d/m/Y'),
                  'tag_no' => $item->tag_no,
                  'company' => [
                      'code' => $item->company_info->code ?? null,
                      'name' => $item->company,
                  ],
                  'payee' => $item->supplier,
                  'bank_name' => $cheque->bank_name,
                  'check_number' => $cheque->cheque_no,
                  'amount' => $cheque->cheque_amount,
                  'cv/ref_no' => $item->voucher_no,
                  'invoice_date' => Carbon::parse($item->document_date ?? $item->pcf_date)->format('d/m/Y'),
                  'account_title' => $item->cheques->first()->account_title
                          ->filter(function ($accountTitle) {
                              return $accountTitle->entry == 'Credit';
                          })
                          ->pluck('account_title_name')
                          ->first() ?? '',
              ];
          });
      });

      if (!$paginate) {
          return $formattedResults;
      }

      return new \Illuminate\Pagination\LengthAwarePaginator(
          $formattedResults->forPage($page, $rows)->values(),
          $formattedResults->count(),
          $rows,
          $page,
          ["path" => $request->url(), "query" => $request->query()]
      );
  }
  private function reportFormatter($data)
  {
    return [
      "tag_no" => $data->tag_no ?? null,
      "receipt_type" => $data->receipt_type ?? null,
      "supplier" => $data->supplier ?? null,
      "voucher_no" => $data->voucher_no ?? null,
      "net_amount" => $data->net_amount ?? null,
      "date_vouchered" => $data->date_vouchered ? Carbon::parse($data->date_vouchered)->format("Y-m-d") : null,
      "bank_name" => $data->bank_name ?? null,
      "cheque_no" => $data->cheque_no ?? null,
      "cheque_amount" => $data->cheque_amount ?? null,
      "cheque_date" =>
        property_exists($data, "cheque_date") && $data->cheque_date
          ? Carbon::parse($data->cheque_date)->format("Y-m-d")
          : null,
      "released_date" =>
        property_exists($data, "released_date") && $data->released_date
          ? Carbon::parse($data->released_date)->format("Y-m-d")
          : null,
      "transaction_type" => $data->transaction_type ?? null,
      "first_name" => $data->first_name ?? null,
      "last_name" => $data->last_name ?? null,
    ];
  }
}
