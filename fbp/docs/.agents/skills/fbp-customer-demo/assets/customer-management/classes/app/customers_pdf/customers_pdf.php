<?php

class customers_pdf
{
	private function tableName(): string {
		return "customers";
	}

	private function statusLabel(Controller $ctl, string $status): string {
		$options = $ctl->get_constant_array("customer_status", false);
		return (string) ($options[$status] ?? $status);
	}

	private function formatter(Controller $ctl): ValueFormatter {
		return $ctl->create_ValueFormatter();
	}

	function list_pdf(Controller $ctl): void {
		$rows = $ctl->db($this->tableName())->getall("id", SORT_ASC);
		$pdf = $ctl->create_pdfmaker();
		$pdf->setPageLayout(["orientation" => "L"]);
		$pdf->addText("Customer List", ["fontsize" => 16, "underline" => true]);

		$table = [[
			"ID",
			"Company Name",
			"Contact Name",
			"Email",
			"Phone",
			"Status",
		]];
		foreach ($rows as $row) {
			$table[] = [
				(string) ($row["id"] ?? ""),
				(string) ($row["company_name"] ?? ""),
				(string) ($row["contact_name"] ?? ""),
				(string) ($row["email"] ?? ""),
				(string) ($row["phone"] ?? ""),
				$this->statusLabel($ctl, (string) ($row["status"] ?? "")),
			];
		}
		$pdf->addTable($table, [
			"margintop" => 10,
			"columnsize" => [14, 24, 16, 24, 12, 10],
		]);
		$pdf->create_pdf();
		$ctl->stop_res = true;
	}

	function detail_pdf(Controller $ctl): void {
		$id = (int) ($ctl->POST("id") ?? 0);
		$row = $ctl->db($this->tableName())->get($id);
		if (empty($row)) {
			header("Content-Type: text/plain; charset=UTF-8");
			echo "Customer not found.";
			$ctl->stop_res = true;
			return;
		}

		$vf = $this->formatter($ctl);
		$pdf = $ctl->create_pdfmaker();
		$pdf->addText("Customer Detail", ["fontsize" => 16, "underline" => true]);
		$pdf->addText("#" . (string) ($row["id"] ?? "") . " / " . (string) ($row["company_name"] ?? ""), [
			"margintop" => 4,
			"fontsize" => 12,
		]);

		$table = [
			["Field", "Value"],
			["ID", (string) ($row["id"] ?? "")],
			["Company Name", (string) ($row["company_name"] ?? "")],
			["Contact Name", (string) ($row["contact_name"] ?? "")],
			["Email", (string) ($row["email"] ?? "")],
			["Phone", (string) ($row["phone"] ?? "")],
			["Postal Code", (string) ($row["postal_code"] ?? "")],
			["Address", (string) ($row["address"] ?? "")],
			["Status", $this->statusLabel($ctl, (string) ($row["status"] ?? ""))],
			["Memo", (string) ($row["memo"] ?? "")],
			["Created At", $vf->format_datetime($row["created_at"] ?? "")],
			["Updated At", $vf->format_datetime($row["updated_at"] ?? "")],
		];
		$pdf->addTable($table, [
			"margintop" => 10,
			"columnsize" => [30, 70],
		]);
		$pdf->create_pdf();
		$ctl->stop_res = true;
	}
}
