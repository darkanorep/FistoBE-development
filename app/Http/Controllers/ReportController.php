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
    $suppliers = $request->input("suppliers", []);

    $page = max(1, (int) $request->input("page", 1));
    $rows = max(1, (int) $request->input("rows", 10));
    $paginate = $request->input("paginate", true);

    if (is_string($suppliers)) {
      $suppliers = json_decode($suppliers, true) ?? [];
    }

    $supplierFilter = "";
    foreach ($suppliers as $supplier) {
      if (!is_numeric($supplier)) {
        $supplierFilter = "";
        break;
      }
      $supplierFilter = "AND transactions.supplier_id IN (" . implode(",", $suppliers) . ")";
    }

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
            users.last_name,
            transactions.date_requested as transaction_date,
            transactions.document_date as invoice_date,
            transactions.business_unit,
            transactions.business_unit_id,
            transactions.company,
            transactions.referrence_no,
            transactions.document_no
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
--         AND (
--               (status IN ('transmit-transmit', 'audit-return', 'release-return', 'file-return') AND transactions.document_id != 8)
--               OR status = 'inspect-inspect'
--         )
        AND DATE_FORMAT(f.date_transmitted, '%Y-%m-%d') BETWEEN :from AND :to
        $supplierFilter
    "),
      [
        "from" => $from,
        "to" => $to,
      ]
    );

    $formattedResults = collect($result)
      ->map(function ($data) {
        return $this->reportFormatter((array) $data);
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
    $suppliers = $request->input("suppliers", []);

    if (is_string($suppliers)) {
      $suppliers = json_decode($suppliers, true) ?? [];
    }

    $supplierFilter = "";
    foreach ($suppliers as $supplier) {
      if (!is_numeric($supplier)) {
        $supplierFilter = "";
        break;
      }
      $supplierFilter = "AND a.supplier_id IN (" . implode(",", $suppliers) . ")";
    }

    $result = DB::select(
      DB::raw("
        SELECT
            MAX(a.id) AS id,
            GROUP_CONCAT(DISTINCT a.tag_no) AS tag_no,
            MAX(a.receipt_type) as receipt_type,
            MAX(a.supplier) AS supplier,
            MAX(a.deleted_at) AS deleted_at,
            MAX(a.company) AS company,
            MAX(a.business_unit) AS business_unit,
            GROUP_CONCAT(DISTINCT a.voucher_no) AS payment_voucher_no,
            GROUP_CONCAT(DISTINCT a.voucher_no) AS voucher_payable_no,
            CONCAT_WS(',', GROUP_CONCAT(DISTINCT a.document_no), GROUP_CONCAT(DISTINCT a.referrence_no)) AS reference_no,
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
--          AND (
--                a.status = 'executive-executive'
--                OR a.status = 'issue-receive'
--            )
          AND a.state != 'void'
          AND c.cheque_date IS NULL
          AND a.deleted_at IS NULL
          AND DATE_FORMAT(h.date_transmitted, '%Y-%m-%d') BETWEEN :from AND :to
            $supplierFilter
        GROUP BY c.cheque_no, c.bank_name
    "),
      [
        "from" => $from,
        "to" => $to,
      ]
    );

    $formattedResults = collect($result)
      ->map(function ($data) {
        return $this->reportFormatter((array) $data);
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
  public function pendingReleaseToTagging(Request $request)
  {
    $from = $request->input("from", Carbon::now()->format("Y-m-d"));
    $to = $request->input("to", Carbon::now()->format("Y-m-d"));
    $suppliers = $request->input("suppliers", []);

    $page = max(1, (int) $request->input("page", 1));
    $rows = max(1, (int) $request->input("rows", 10));
    $paginate = $request->input("paginate", true);

    if (is_string($suppliers)) {
      $suppliers = json_decode($suppliers, true) ?? [];
    }

    $supplierFilter = "";
    foreach ($suppliers as $supplier) {
      if (!is_numeric($supplier)) {
        $supplierFilter = "";
        break;
      }
      $supplierFilter = "AND a.supplier_id IN (" . implode(",", $suppliers) . ")";
    }

    $result = DB::select(
      DB::raw("
        SELECT
            MAX(a.id) AS id,
            MAX(a.updated_at) AS updated_at,
            GROUP_CONCAT(DISTINCT a.tag_no) AS tag_no,
            MAX(a.receipt_type) as receipt_type,
            MAX(a.supplier) AS supplier,
            MAX(a.deleted_at) AS deleted_at,
            MAX(a.company) AS company,
            MAX(a.business_unit) AS business_unit,
            GROUP_CONCAT(DISTINCT a.voucher_no) AS payment_voucher_no,
            GROUP_CONCAT(DISTINCT a.voucher_no) AS voucher_payable_no,
            CONCAT_WS(',', GROUP_CONCAT(DISTINCT a.document_no), GROUP_CONCAT(DISTINCT a.referrence_no)) AS reference_no,
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
--                OR a.status = 'issue-receive'
            )
          AND a.state != 'void'
          AND c.cheque_date IS NULL
          AND a.deleted_at IS NULL
          AND DATE_FORMAT(a.updated_at, '%Y-%m-%d') BETWEEN :from AND :to
            $supplierFilter
        GROUP BY c.cheque_no, c.bank_name
    "),
      [
        "from" => $from,
        "to" => $to,
      ]
    );

    $formattedResults = collect($result)
      ->map(function ($data) {
        return $this->reportFormatter((array) $data);
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
    $suppliers = $request->input("suppliers", []);

    $page = max(1, (int) $request->input("page", 1));
    $rows = max(1, (int) $request->input("rows", 10));
    $paginate = $request->input("paginate", true);

    if (is_string($suppliers)) {
      $suppliers = json_decode($suppliers, true) ?? [];
    }

    $supplierFilter = "";
    foreach ($suppliers as $supplier) {
      if (!is_numeric($supplier)) {
        $supplierFilter = "";
        break;
      }
      $supplierFilter = "AND a.supplier_id IN (" . implode(",", $suppliers) . ")";
    }

    $result = DB::select(
      DB::raw("
                SELECT
                    MAX(a.id) AS id,
                    GROUP_CONCAT(DISTINCT a.tag_no) AS tag_no,
                    GROUP_CONCAT(DISTINCT a.voucher_no) AS payment_voucher_no,
                    GROUP_CONCAT(DISTINCT a.voucher_no) AS voucher_payable_no,
                    CONCAT_WS(',', GROUP_CONCAT(DISTINCT a.document_no), GROUP_CONCAT(DISTINCT a.referrence_no)) AS reference_no,
                    MAX(a.company) AS company,
                    MAX(a.business_unit) AS business_unit,
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
                    MAX(d.created_at) AS released_date
--                    MAX(f.created_at) AS date_vouchered
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
--                AND (
--                       a.status = 'issue-issue'
--                       OR a.status = 'release-receive'
--                  )
                  AND a.state != 'void'
                  AND c.cheque_date IS NOT NULL
                  AND c.deleted_at IS NULL
                  AND a.deleted_at IS NULL
                  AND DATE_FORMAT(d.created_at, '%Y-%m-%d') BETWEEN :from AND :to
                    $supplierFilter
                GROUP BY c.cheque_no, c.bank_name
            "),
      [
        "from" => $from,
        "to" => $to,
      ]
    );

    $formattedResults = collect($result)
      ->map(function ($data) {
        return $this->reportFormatter((array) $data);
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
  public function pendingReleaseToSupplier(Request $request)
  {
    $from = $request->input("from", Carbon::now()->format("Y-m-d"));
    $to = $request->input("to", Carbon::now()->format("Y-m-d"));
    $suppliers = $request->input("suppliers", []);

    $page = max(1, (int) $request->input("page", 1));
    $rows = max(1, (int) $request->input("rows", 10));
    $paginate = $request->input("paginate", true);

    if (is_string($suppliers)) {
      $suppliers = json_decode($suppliers, true) ?? [];
    }

    $supplierFilter = "";
    foreach ($suppliers as $supplier) {
      if (!is_numeric($supplier)) {
        $supplierFilter = "";
        break;
      }
      $supplierFilter = "AND a.supplier_id IN (" . implode(",", $suppliers) . ")";
    }

    $result = DB::select(
      DB::raw("
                SELECT
                    MAX(a.id) AS id,
                    GROUP_CONCAT(DISTINCT a.tag_no) AS tag_no,
                    GROUP_CONCAT(DISTINCT a.voucher_no) AS payment_voucher_no,
                    GROUP_CONCAT(DISTINCT a.voucher_no) AS voucher_payable_no,
                    CONCAT_WS(',', GROUP_CONCAT(DISTINCT a.document_no), GROUP_CONCAT(DISTINCT a.referrence_no)) AS reference_no,
                    MAX(a.company) AS company,
                    MAX(a.business_unit) AS business_unit,
                    MAX(a.tag_no) AS tag_no,
                    MAX(a.receipt_type) as receipt_type,
                    MAX(a.supplier) AS supplier,
                    MAX(a.deleted_at) AS deleted_at,
                    MAX(a.voucher_no) AS voucher_no,
                    MAX(a.voucher_month) AS voucher_month,
                    MAX(a.status) AS status,
                    MAX(a.updated_at) AS updated_at,
                    c.bank_name,
                    c.cheque_no,
                    MAX(c.cheque_amount) AS cheque_amount,
                    MAX(c.cheque_date) AS cheque_date
--                    MAX(d.created_at) AS released_date
--                    MAX(f.created_at) AS date_vouchered
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
--                       OR a.status = 'release-receive'
               )
                  AND a.state != 'void'
                  AND c.cheque_date IS NOT NULL
                  AND c.deleted_at IS NULL
                  AND a.deleted_at IS NULL
                  AND DATE_FORMAT(a.updated_at, '%Y-%m-%d') BETWEEN :from AND :to
                    $supplierFilter
                GROUP BY c.cheque_no, c.bank_name
            "),
      [
        "from" => $from,
        "to" => $to,
      ]
    );

    $formattedResults = collect($result)
      ->map(function ($data) {
        return $this->reportFormatter((array) $data);
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
  public function taggingReleasing(Request $request)
  {
    $from = $request->input("from", Carbon::now()->format("Y-m-d"));
    $to = $request->input("to", Carbon::now()->format("Y-m-d"));
    $suppliers = $request->input("suppliers", []);

    $page = max(1, (int) $request->input("page", 1));
    $rows = max(1, (int) $request->input("rows", 10));
    $paginate = $request->input("paginate", true);

    if (is_string($suppliers)) {
      $suppliers = json_decode($suppliers, true) ?? [];
    }

    $supplierFilter = "";
    foreach ($suppliers as $supplier) {
      if (!is_numeric($supplier)) {
        $supplierFilter = "";
        break;
      }
      $supplierFilter = "AND a.supplier_id IN (" . implode(",", $suppliers) . ")";
    }

    $result = DB::select(
      DB::raw("
                SELECT
                    MAX(a.id) AS id,
                    GROUP_CONCAT(DISTINCT a.tag_no) AS tag_no,
                    GROUP_CONCAT(DISTINCT a.voucher_no) AS payment_voucher_no,
                    GROUP_CONCAT(DISTINCT a.voucher_no) AS voucher_payable_no,
                    CONCAT_WS(',', GROUP_CONCAT(DISTINCT a.document_no), GROUP_CONCAT(DISTINCT a.referrence_no)) AS reference_no,
                    MAX(a.company) AS company,
                    MAX(a.business_unit) AS business_unit,
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
                    MAX(d.created_at) AS released_date
--                    MAX(f.created_at) AS date_vouchered
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
                    FROM releases c
                    WHERE c.status = 'release-release'
                      AND c.created_at = (
                          SELECT MAX(c2.created_at)
                          FROM releases c2
                          WHERE c2.transaction_id = c.transaction_id
                            AND c2.status = 'release-release'
                      )
                ) d ON a.id = d.transaction_id
                LEFT JOIN (
                    SELECT e.id, e.transaction_id, e.created_at
                    FROM associates e
                    WHERE status = 'voucher-voucher'
                ) f ON a.id = f.transaction_id
                WHERE a.deleted_at IS NULL
--                AND (
--                       a.status = 'issue-issue'
--                       OR a.status = 'release-receive'
--                  )
                  AND a.state != 'void'
                  AND c.cheque_date IS NOT NULL
                  AND c.deleted_at IS NULL
                  AND a.deleted_at IS NULL
                  AND DATE_FORMAT(d.created_at, '%Y-%m-%d') BETWEEN :from AND :to
                    $supplierFilter
                GROUP BY c.cheque_no, c.bank_name
            "),
      [
        "from" => $from,
        "to" => $to,
      ]
    );

    $formattedResults = collect($result)
      ->map(function ($data) {
        return $this->reportFormatter((array) $data);
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
  public function chequeCreated(Request $request)
  {
    $from = date($request->input("from", Carbon::now()->format("Y-m-d")));
    $to = date($request->input("to", Carbon::now()->format("Y-m-d")));
    $suppliers = $request->input("suppliers");

    if (is_string($suppliers)) {
      $suppliers = json_decode($suppliers, true) ?? [];
    }
    $page = max(1, (int) $request->input("page", 1));
    $rows = max(1, (int) $request->input("rows", 10));
    $paginate = $request->input("paginate", true);

    $transactions = Transaction::with([
      "transmit",
      "treasuryAssociates",
      "businessUnit",
      "account_titles",
      "company_info:id,code",
      "chequeHistory",
      "voucher",
      "treasuryCheque",
      "company_info",
      "cheques" => function ($query) {
        $query->with(["account_title"]);
      },
    ])
      ->whereHas("chequeHistory", function ($query) use ($from, $to) {
        $query->whereDate("created_at", ">=", $from)->whereDate("created_at", "<=", $to);
      })
      ->when(!empty($suppliers), function ($query) use ($suppliers) {
        $query->whereIn("supplier_id", $suppliers);
      })
      ->select(
        "id",
        "tag_no",
        "company_id",
        "company",
        "supplier_id",
        "supplier",
        "remarks",
        "voucher_no",
        "document_date",
        "pcf_date",
        "receipt_type",
        "business_unit_id",
        "business_unit",
        "referrence_no",
        "document_no",
        "document_date",
        "date_requested",
        "assigned_id",
        "transaction_type"
      )
      ->get();

    $result = $transactions
      ->groupBy(function ($item) {
        $cheque = $item->treasuryCheque->first();
        return ($cheque ? $cheque->bank_name : "") . "-" . ($cheque ? $cheque->cheque_no : "");
      })
      ->map(function ($group) {
        return [
          "date_vouchered" => Carbon::parse($this->getDateEveryStatus($group->first()->voucher, "voucher-voucher"))
            ->setTimezone("Asia/Manila")
            ->format("Y-m-d"),
          "date_received" => Carbon::parse($this->getDateEveryStatus($group->first()->cheques, "cheque-receive"))
            ->setTimezone("Asia/Manila")
            ->format("d/m/Y"),
          "transaction_date" => Carbon::parse($group->first()->date_requested)
            ->setTimezone("Asia/Manila")
            ->format("Y-m-d"),
          "date_transmitted" => Carbon::parse(
            $this->getDateEveryStatus($group->first()->transmit, "transmit-transmit")
          ),
          "business_unit_code" =>
            $group
              ->pluck("businessUnit.code")
              ->unique()
              ->values()
              ->first() ??
            $group
              ->pluck("company_info.code")
              ->unique()
              ->values()
              ->first(),
          "business_unit" =>
            $group
              ->pluck("business_unit")
              ->unique()
              ->values()
              ->first() ??
            $group
              ->pluck("company")
              ->unique()
              ->values()
              ->first(),
          "receipt_type" => $group
            ->pluck("receipt_type")
            ->unique()
            ->values()
            ->first(),
          "supplier" => $group
            ->pluck("supplier")
            ->unique()
            ->values()
            ->first(),
          "bank_name" => optional($group->first()->treasuryCheque->first())->bank_name,
          "cheque_no" => optional($group->first()->treasuryCheque->first())->cheque_no,
          "cheque_amount" => optional($group->first()->treasuryCheque->first())->cheque_amount,
          "cheque_date" => optional($group->first()->treasuryCheque->first())->cheque_date,
          "tag_no" => $group
            ->pluck("tag_no")
            ->unique()
            ->values()
            ->all(),
          "payment_voucher_no" => $group
            ->pluck("voucher_no")
            ->unique()
            ->values()
            ->all(),
          "voucher_payable_no" => $group
            ->pluck("voucher_no")
            ->unique()
            ->values()
            ->all(),
          "reference_no" => $group
            ->pluck("document_no")
            ->merge($group->pluck("referrence_no"))
            ->filter()
            ->unique()
            ->values()
            ->all(),
          "invoice_date" => $group
            ->pluck("document_date")
            ->filter()
            ->unique()
            ->values()
            ->all(),
          "account_titles" => $group
            ->flatMap(function ($item) {
              return optional($item->account_titles)
                ->filter(function ($accountTitle) {
                  return strtolower($accountTitle->entry) === "debit";
                })
                ->pluck("account_title_name");
            })
            ->unique()
            ->values()
            ->all(),
          "first_name" => $group
            ->pluck("treasuryAssociates.first_name")
            ->unique()
            ->values()
            ->first(),
          "last_name" => $group
            ->pluck("treasuryAssociates.last_name")
            ->unique()
            ->values()
            ->first(),
          "transaction_type" => $group
            ->pluck("transaction_type")
            ->unique()
            ->values()
            ->first(),
          //              'net_amount' => $group->sum(function ($item) {
          //                  return optional($item->account_titles)
          //                      ->filter(function ($accountTitle) {
          //                          return strtolower($accountTitle->entry) === 'credit'
          //                              && str_contains(strtolower($accountTitle->account_title_name), 'payable');
          //                      })
          //                      ->sum('amount');
          //              }),
        ];
      })
      ->values();

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
  public function chequeCleared(Request $request)
  {
    $from = date($request->input("from", Carbon::now()->format("Y-m-d")));
    $to = date($request->input("to", Carbon::now()->format("Y-m-d")));
    $suppliers = $request->input("suppliers", []);

    $page = max(1, (int) $request->input("page", 1));
    $rows = max(1, (int) $request->input("rows", 10));
    $paginate = $request->input("paginate", true);

    $clearedCheques = DB::table("transactions")
      ->when(!empty($suppliers), function ($query) use ($suppliers) {
        $query->whereIn("supplier_id", $suppliers);
      })
      ->where("transactions.state", "!=", "void")
      ->leftJoin("cheques", function ($join) {
        $join->on("transactions.id", "=", "cheques.transaction_id")->where("cheques.deleted_at", "=", null);
      })
      ->join(
        DB::raw("(SELECT transaction_id, MAX(id) as latest_id
                 FROM clears
                 WHERE status = 'clear-clear'
                 GROUP BY transaction_id) as latest_clear"),
        function ($join) {
          $join->on("transactions.id", "=", "latest_clear.transaction_id");
        }
      )
      ->leftJoin("clears", function ($join) {
        $join->on("transactions.id", "=", "clears.transaction_id");
      })
      ->leftJoin("clearing_account_titles", function ($join) {
        $join
          ->on("clears.id", "=", "clearing_account_titles.clear_id")
          ->whereColumn("cheques.updated_at", "=", "clearing_account_titles.created_at")
          ->orWhereColumn("cheques.id", "=", "clearing_account_titles.cheque_id");
      })
      ->leftJoin("users", function ($join) {
        $join
          ->on("clears.user_id", "=", "users.id")
          ->select("users.id_prefix", "users.id_no", "users.first_name", "users.last_name");
      })
      //          ->leftJoin('p_o_batches', function ($join) {
      //              $join->on('transactions.request_id', '=', 'p_o_batches.request_id')
      //                  ->where('p_o_batches.deleted_at', '=', null);
      //          })
      //          ->leftJoin('received_receipts', function ($join) {
      //              $join->on('transactions.id', '=', 'received_receipts.transaction_id')
      //                  ->select('received_receipts.id', 'received_receipts.rr_number')
      //                  ->leftJoin('purchase_orders', function ($join) {
      //                      $join->on('received_receipts.id', '=', 'purchase_orders.received_receipt_id')
      //                          ->select('purchase_orders.id', 'purchase_orders.po_number');
      //                  })->leftJoin('job_orders', function ($join) {
      //                      $join->on('received_receipts.id', '=', 'job_orders.received_receipt_id')
      //                          ->select('job_orders.id', 'job_orders.jo_number');
      //                  });
      //          })
      ->leftJoin("account_titles", function ($join) {
        $join->on("clearing_account_titles.account_title_id", "=", "account_titles.id");
      })
      ->where(function ($query) use ($from, $to) {
        $query->whereDate("cheques.date_cleared", ">=", $from)->whereDate("cheques.date_cleared", "<=", $to);
      })
      ->select(
        "users.id_prefix",
        "users.id_no",
        "users.first_name",
        "users.last_name",
        "transactions.tag_no",
        "transactions.receipt_type",
        "transactions.date_requested",
        "transactions.supplier",
        "transactions.referrence_no",
        "transactions.voucher_no",
        "transactions.voucher_month",
        "transactions.capex_no",
        "transactions.document_no",
        "transactions.utilities_receipt_no",
        "transactions.company",
        "transactions.business_unit",
        "transactions.pcf_letter",
        "transactions.pcf_date",
        "clearing_account_titles.account_title_name",
        "clearing_account_titles.id",
        "clearing_account_titles.amount",
        "clearing_account_titles.entry",
        "clearing_account_titles.remarks",
        "account_titles.code as account_title_code",
        "clearing_account_titles.account_title_name",
        "clearing_account_titles.company_code",
        "clearing_account_titles.company_name",
        "clearing_account_titles.department_code",
        "clearing_account_titles.department_name",
        "clearing_account_titles.location_code",
        "clearing_account_titles.location_name",
        "clearing_account_titles.business_unit_code",
        "clearing_account_titles.business_unit_name",
        "clearing_account_titles.sub_unit_code",
        "clearing_account_titles.sub_unit_name",
        //              "p_o_batches.po_no",
        //              "p_o_batches.rr_group",
        //              "received_receipts.rr_number as rr_number",
        //              "purchase_orders.po_number as po_number",
        //              "job_orders.jo_number as jo_number",
        "cheques.bank_name",
        "cheques.cheque_no",
        "cheques.cheque_date",
        "cheques.cheque_amount",
        "cheques.date_cleared",
        "cheques.date_cleared as released_date"
      )
      ->get();

    $result = $clearedCheques
      ->groupBy(function ($item) {
        return $item->bank_name . "-" . $item->cheque_no;
      })
      ->map(function ($item) {
        return [
          //                "date_vouchered" => Carbon::parse($item->first()->date_requested)->format("Y-m-d"),
          //                "transaction_date" => Carbon::parse($item->first()->date_requested)->format("Y-m-d"),
          //                "date_received" => Carbon::parse($item->first()->date_cleared)->format("d/m/Y"),
          //                "date_transmitted" => null,
          //                "released_date" => Carbon::parse($item->first()->released_date)->format("Y-m-d"),
          "date_cleared" => Carbon::parse($item->first()->date_cleared)->format("Y-m-d"),
          "business_unit_code" => $item->first()->business_unit_code ?? null,
          "business_unit" => $item->first()->business_unit ?? ($item->first()->company ?? null),
          "tag_no" => $item
            ->pluck("tag_no")
            ->unique()
            ->values()
            ->all(),
          "receipt_type" => $item->first()->receipt_type ?? null,
          "supplier" => $item->first()->supplier ?? null,
          "voucher_no" => $item->first()->voucher_no ?? null,
          "payment_voucher_no" => $item
            ->pluck("voucher_no")
            ->unique()
            ->values()
            ->all(),
          "voucher_payable_no" => $item
            ->pluck("voucher_no")
            ->unique()
            ->values()
            ->all(),
          "reference_no" => $item
            ->pluck("document_no")
            ->merge($item->pluck("referrence_no"))
            ->filter()
            ->unique()
            ->values()
            ->all(),
          "invoice_date" => $item
            ->pluck("document_date")
            ->filter()
            ->unique()
            ->values()
            ->all(),
          "transaction_type" => null,
          "last_name" => $item->first()->last_name ?? null,
          "first_name" => $item->first()->first_name ?? null,
          "bank_name" => $item->first()->bank_name ?? null,
          "cheque_no" => $item->first()->cheque_no ?? null,
          "cheque_date" => Carbon::parse($item->first()->cheque_date)->format("Y-m-d"),
          "cheque_amount" => $item->first()->cheque_amount ?? null,
          "account_titles" => $item
            ->filter(function ($title) {
              return $title->entry == "Credit";
            })
            ->pluck("account_title_name")
            ->unique()
            ->values()
            ->all(),
        ];
      })
      ->values();

    $formattedResults = collect($result)
      ->map(function ($data) {
        return $this->reportFormatter((array) $data);
      })
      ->values();

    if (!$paginate) {
      return $formattedResults;
    }

    // Add this return for paginated response
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
      "date_vouchered" => !empty($data["date_vouchered"])
        ? Carbon::parse($data["date_vouchered"])->format("Y-m-d")
        : null,
      "transaction_date" => !empty($data["transaction_date"])
        ? Carbon::parse($data["transaction_date"])->format("Y-m-d")
        : null,
      "date_received" => !empty($data["date_received"])
        ? Carbon::createFromFormat("d/m/Y", $data["date_received"])->format("Y-m-d")
        : null,
      "date_transmitted" => !empty($data["date_transmitted"])
        ? Carbon::parse($data["date_transmitted"])->format("Y-m-d")
        : null,
      "date_released" => !empty($data["released_date"]) ? Carbon::parse($data["released_date"])->format("Y-m-d") : null,
      "date_cleared" => !empty($data["date_cleared"]) ? Carbon::parse($data["date_cleared"])->format("Y-m-d") : null,
      "business_unit_code" => $data["business_unit_code"] ?? null,
      "business_unit" => $data["business_unit"] ?? ($data["company"] ?? null),
      "accounting_tag" => !empty($data["tag_no"])
        ? (is_array($data["tag_no"])
          ? $data["tag_no"]
          : explode(",", $data["tag_no"]))
        : [],
      "receipt_type" => $data["receipt_type"] ?? null,
      "supplier" => $data["supplier"] ?? null,
      "voucher_no" => $data["voucher_no"] ?? null,
      "payment_voucher_no" => !empty($data["payment_voucher_no"])
        ? (is_array($data["payment_voucher_no"])
          ? $data["payment_voucher_no"]
          : explode(",", $data["payment_voucher_no"]))
        : [],
      "voucher_payable_no" => !empty($data["voucher_payable_no"])
        ? (is_array($data["voucher_payable_no"])
          ? $data["voucher_payable_no"]
          : explode(",", $data["voucher_payable_no"]))
        : [],
      "reference_no" => !empty($data["reference_no"])
        ? (is_array($data["reference_no"])
          ? $data["reference_no"]
          : explode(",", $data["reference_no"]))
        : [],
      "invoice_date" => $data["invoice_date"] ?? null,
      "transaction_type" => $data["transaction_type"] ?? null,
      "last_name" => $data["last_name"] ?? null,
      "first_name" => $data["first_name"] ?? null,
      "bank_name" => $data["bank_name"] ?? null,
      "cheque_no" => $data["cheque_no"] ?? null,
      "cheque_date" => !empty($data["cheque_date"]) ? Carbon::parse($data["cheque_date"])->format("Y-m-d") : null,
      "cheque_amount" => $data["cheque_amount"] ?? null,
      "net_amount" => $data["net_amount"] ?? null,
      "account_titles" => !empty($data["account_titles"])
        ? (is_array($data["account_titles"])
          ? $data["account_titles"]
          : explode(",", $data["account_titles"]))
        : [],
    ];
  }
    public function auditReport(Request $request) {

        $from = date($request->input("from", Carbon::now()->format("Y-m-d")));
        $to = date($request->input("to", Carbon::now()->format("Y-m-d")));
        $suppliers = $request->input("suppliers", []);


        $page = max(1, (int) $request->input("page", 1));
        $rows = max(1, (int) $request->input("rows", 10));
        $paginate = $request->input("paginate", true);

       $transactions = Transaction::withTrashed()
            ->whereHas('cheques.cheques', function ($query) use ($from, $to) {
                $query->whereBetween('cheque_date', [$from, $to]);
            })
            ->with([
                'cheques' => function ($query) {
                    $query->where('status', 'cheque-cheque')
                        ->select([
                            'id',
                            'transaction_id',
                            'created_at as cheque_date',
                            'status'
                        ]);
                },
                'cheques.cheques' => function ($query) {
                    $query->withTrashed()->select([
                        'treasury_id',
                        'bank_name',
                        'cheque_no',
                        'cheque_amount',
                        'cheque_date',
                        'is_received',
                        'is_audited',
                        'is_executived',
                        'is_issued',
                        'is_cleared',
                        'is_released',
                        'reason_id'
                    ]);
                },
                'tag' => function ($query) {
                    $query->where('status', 'tag-tag')
                    ->select([
                        'transaction_id',
                        'status',
                        'created_at as tagged_date'
                    ]);
                },
                'extract' => function ($query) {
                    $query->where('status', 'extract-extract')
                    ->select([
                        'transaction_id',
                        'status',
                        'created_at as transmitted_date'
                    ]);
                },
                'transmit' => function ($query) {
                    $query->where('status', 'transmit-transmit')
                    ->select([
                        'transaction_id',
                        'status',
                        'created_at as transmitted_date'
                    ]);
                },
                'voucher' => function ($query) {
                    $query->select([
                        'transaction_id',
                        'status',
                        'created_at'
                    ]);
                },
                'approve' => function ($query) {
                    $query->where('status', 'approve-approve')
                    ->select([
                        'transaction_id',
                        'status',
                        'created_at as approved_date'
                    ]);
                },
                'audit' => function ($query) {
                    $query
                        ->where('status', 'audit-audit')
                        ->select([
                        'transaction_id',
                        'status',
                        'created_at as audited_date'
                    ]);
                },
                'executive' => function ($query) {
                    $query
                        ->where('status', 'executive-executive')
                        ->select([
                            'transaction_id',
                            'status',
                            'created_at as signed_date'
                        ]);
                },
                'release' => function ($query) {
                    $query
                        ->where('status', 'release-release')
                        ->select([
                            'transaction_id',
                            'status',
                            'created_at as released_date'
                        ]);
                },
                'discharge' => function ($query) {
                    $query
                        ->where('status', 'discharge-discharge')
                        ->select([
                            'transaction_id',
                            'status',
                            'created_at as discharged_date'
                        ]);
                },
                'file' => function ($query) {
                    $query->where('status', 'file-file')
                        ->select([
                            'transaction_id',
                            'status',
                            'created_at as filed_date'
                        ]);
                }
            ])
            ->select([
                'id',
                'tag_no',
                'voucher_no',
                'document_no',
                'referrence_no',
                'business_unit',
                'remarks',
                'supplier',
                'document_amount',
                'referrence_amount',
                'status'
            ])->get();

        // Transform to return latest cheque as object instead of array
        $finalCollection = $transactions->flatMap(function ($transaction) {
            $data = $transaction->toArray();
            $latestCheque = $transaction->cheques->sortByDesc('created_at')->first();
            $cheques = $latestCheque ? ($latestCheque->cheques ?? collect()) : collect();

            $latestVoucherReceive = $transaction->voucher->where('status', 'voucher-receive')->sortByDesc('created_at')->first();
            $latestVoucherCreate = $transaction->voucher->where('status', 'voucher-voucher')->sortByDesc('created_at')->first();

            $data['tag'] = $transaction->tag->sortByDesc('tagged_date')->first();
            $data['extract'] = $transaction->extract->sortByDesc('extracted_date')->first();
            $data['transmit'] = $transaction->transmit->sortByDesc('transmitted_date')->first();
            $data['voucher'] = $latestVoucherCreate ? $latestVoucherCreate->created_at : null;
            $data['voucher_receive'] = $latestVoucherReceive ? $latestVoucherReceive->created_at : null;
            $data['approve'] = $transaction->approve->sortByDesc('approved_date')->first();
            $data['audit'] = $transaction->audit->sortByDesc('audited_date')->first();
            $data['executive'] = $transaction->executive->sortByDesc('signed_date')->first();
            $data['release'] = $transaction->release->sortByDesc('released_date')->first();
            $data['discharge'] = $transaction->discharge->sortByDesc('discharged_date')->first();
            $data['file'] = $transaction->file->sortByDesc('filed_date')->first();
            $data['cheque'] = $latestCheque;
            $data['status'] = !empty($latestCheque->reason_id) ? 'cancelled' : $transaction->status;
            unset($data['cheques']);

            $allCheques = $transaction->cheques->flatMap(function ($chequeRecord) {
                return $chequeRecord->cheques ?? collect();
            });

            if ($allCheques->isEmpty()) {
                $data['single_cheque'] = null;
                $data['cheque'] = null;
                $data['status'] = $transaction->status;
                return [$data];
            }

            return $allCheques->map(function ($cheque) use ($data, $transaction) {
                $row = $data;
                $row['single_cheque'] = $cheque;
                // Find the corresponding cheque record
                $chequeRecord = $transaction->cheques->find($cheque->treasury_id);
                $row['cheque'] = $chequeRecord;
                // Set status per cheque
                $row['status'] = !empty($cheque->reason_id) ? ($cheque->reason_id == 2 ? 'void' : 'cancelled') : 'pending';
                return $row;
            })->values()->all();

        })
            ->map(function ($item) {
            return [
                'date_transmitted' => !empty($item['transmit']['transmitted_date']) ? Carbon::parse($item['transmit']['transmitted_date'])->format('Y-m-d') : null,
                'date_received' => $item['voucher_receive'] ? Carbon::parse($item['voucher_receive'])->format('Y-m-d') : null,
                'date_processed' => !empty($item['approve']['approved_date']) ? Carbon::parse($item['approve']['approved_date'])->format('Y-m-d') : null,
                'tag_no' => $item['tag_no'] ?? null,
                'voucher_no' => $item['voucher_no'] ?? null,
                'invoice_no' => $item['referrence_no'] ?? $item['document_no'] ?? null,
                'operating_unit' => $item['business_unit'] ?? null,
                'description' => $item['remarks'] ?? null,
                'payee' => $item['supplier'] ?? null,
                'amount' => $item['document_amount'] ?? $item['referrence_amount'] ?? null,
                'bank_name' => $item['single_cheque']['bank_name'] ?? null,
                'cheque_no' => $item['single_cheque']['cheque_no'] ?? null,
//                'cheque_amount' => $item['single_cheque']['cheque_amount'] ?? null,
                'cheque_date' => !empty($item['single_cheque']['cheque_date']) ? Carbon::parse($item['single_cheque']['cheque_date'])->format('Y-m-d') : null,
                'no_of_processing_days' => $item['voucher_receive'] && !empty($item['approve']['approved_date'])
                    ? Carbon::parse($item['approve']['approved_date'])->diffInDays(Carbon::parse($item['voucher_receive']))
                    : null,
                'status' => $item['status'] ?? null,
                'tagging_of_document_date' => !empty($item['tag']['tagged_date']) ? Carbon::parse($item['tag']['tagged_date'])->format('Y-m-d') : null,
                'transmittal_of_official_receipt_date' => !empty($item['extract']['extracted_date']) ? Carbon::parse($item['extract']['extracted_date'])->format('Y-m-d') : null,
                'transmittal_of_gas_receipt_date' => !empty($item['transmit']['transmitted_date']) ? Carbon::parse($item['transmit']['transmitted_date'])->format('Y-m-d') : null,

//                'creation_of_voucher_date' => !empty($item['voucher']['vouchered_date']) ? Carbon::parse($item['voucher']['vouchered_date'])->format('Y-m-d') : null,
                'creation_of_voucher_date' => $item['voucher'] ? Carbon::parse($item['voucher'])->format('Y-m-d') : null,

                'approval_of_voucher_date' => !empty($item['approve']['approved_date']) ? Carbon::parse($item['approve']['approved_date'])->format('Y-m-d') : null,
                'transmittal_of_document_date' => !empty($item['transmit']['transmitted_date']) ? Carbon::parse($item['transmit']['transmitted_date'])->format('Y-m-d') : null,
                'creation_of_cheque_date' => !empty($item['cheque']['cheque_date']) ? Carbon::parse($item['cheque']['cheque_date'])->format('Y-m-d') : null,
                'auditing_of_cheque_date' => !empty($item['audit']['audited_date']) ? Carbon::parse($item['audit']['audited_date'])->format('Y-m-d') : null,
                'signing_of_cheque_date' => !empty($item['executive']['signed_date']) ? Carbon::parse($item['executive']['signed_date'])->format('Y-m-d') : null,
                'releasing_of_cheque_date' => !empty($item['release']['released_date']) ? Carbon::parse($item['release']['released_date'])->format('Y-m-d') : null,
                'transmittal_of_official_voucher_date' => !empty($item['discharge']['discharged_date']) ? Carbon::parse($item['discharge']['discharged_date'])->format('Y-m-d') : null,
                'filing_of_voucher_date' => !empty($item['file']['filed_date']) ? Carbon::parse($item['file']['filed_date'])->format('Y-m-d') : null,
            ];
        })->filter(function ($item) use ($from, $to) {
            $chequeDate = $item['cheque_date'];
            return $chequeDate !== null && $chequeDate >= $from && $chequeDate <= $to;
        })->values();

        if (!$paginate) {
            return $finalCollection;
        }

        return new \Illuminate\Pagination\LengthAwarePaginator(
            $finalCollection->forPage($page, $rows)->values(),
            $finalCollection->count(),
            $rows,
            $page,
            ["path" => $request->url(), "query" => $request->query()]
        );
    }
}
