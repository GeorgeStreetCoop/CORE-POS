<?php
/*******************************************************************************

    Copyright 2016 George Street Co-op

    This file is part of IT CORE.

    IT CORE is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    IT CORE is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    in the file license.txt along with IT CORE; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

*********************************************************************************/

/**
  @class DefaultTenderReport
  Generate a tender report
*/
class GeorgeStreetTenderReport extends TenderReport {

/** 
 Print a tender report

 This tender report is based on a single tender tape view
 rather than multiple views (e.g. ckTenders, ckTenderTotal, etc).
 Which tenders to include is defined via checkboxes by the
 tenders on the install page's "extras" tab.
 
 The only differences between this report and DefaultTenderReport are formatting:
 1. Cash line items are omitted entirely; just the sum is shown
 2. All items are shown in a less paper-intensive (more compact) format.
 3. Cashier ID and timestamp are made bigger and book-end the report.
 */
static public function get()
{
    $receipt = ReceiptLib::biggerFont("Transaction Summary")."\n\n";
    $receipt .= ReceiptLib::biggerFont(date('D M j Y - g:ia'))."\n\n";
	$report_params = array();

	$lane_db = Database::tDataConnect();
    if ($lane_db->isConnected('core_translog')) {
	    $this_lane = CoreLocal::get('laneno');
		$transarchive = 'localtranstoday';
		$opdata = 'core_opdata';
		$report_params += array(
			"Lane {$this_lane} tender" => "
					SELECT
						'Lane {$this_lane} tenders' Plural,
						DATE_FORMAT(d.datetime, '%Y-%m-%d') TransDate,
						t.TenderName GroupLabel,
						COUNT(*) GroupQuantity,
						'transaction' GroupQuantityLabel,
						-SUM(d.total) GroupValue
					FROM {$transarchive} d
						LEFT JOIN {$opdata}.tenders t ON d.trans_subtype = t.TenderCode
					WHERE d.emp_no != 9999 AND (TRUE AND TRUE)
						AND d.trans_status != 'X'
						AND d.trans_type = 'T'
						AND DATE_FORMAT(d.datetime, '%Y-%m-%d') = (SELECT MAX(DATE_FORMAT(datetime, '%Y-%m-%d')) FROM {$transarchive})
					GROUP BY t.tenderName
					ORDER BY
						FIELD(d.trans_subtype, 'CA', 'CK', 'CC', 'DC', 'EF', d.trans_subtype),
						d.trans_subtype
				",
			);
	}

	$office_host = CoreLocal::get('mServer');
	$office_ping = shell_exec("ping -q -t2 -c3 {$office_host}");
	$office_live = (preg_match('~0 packets received~', $office_ping)? false : true);

	if ($office_live) {
		$office_db = Database::mDataConnect();
	    if (!$office_db->isConnected('office_trans')) {
	    	$office_live = false;
	    }
	}

	if ($office_live) {
		$transarchive = 'dtransactions';
		$opdata = 'office_opdata';
	}
	else {
		$receipt .= "\n";
		$receipt .= ReceiptLib::boldFont();
		$receipt .= "Server is unavailable; printing lane data only.";
		$receipt .= ReceiptLib::normalFont();
		$receipt .= "\n";

		$office_db = Database::tDataConnect();
		$transarchive = 'localtrans';
		$opdata = 'core_opdata';
	}

	$report_params += array(
		'department' => "
					SELECT
						'departments' Plural,
						DATE_FORMAT(d.datetime, '%Y-%m-%d') TransDate,
						CONCAT_WS(' ', t.dept_no, t.dept_name) GroupLabel,
						SUM(IF(d.department IN (102, 113) OR d.scale = 1, 1, d.quantity)) GroupQuantity,
						'item' GroupQuantityLabel,
						SUM(d.total) GroupValue
					FROM {$transarchive} d
						LEFT JOIN {$opdata}.departments t ON d.department=t.dept_no
					WHERE d.emp_no != 9999 AND (TRUE AND TRUE)
						AND d.trans_status != 'X'
						AND d.department != 0
						AND DATE_FORMAT(d.datetime, '%Y-%m-%d') = (SELECT MAX(DATE_FORMAT(datetime, '%Y-%m-%d')) FROM {$transarchive})
					GROUP BY t.dept_no
				",
		'tax' => "
					SELECT
						'taxes' Plural,
						DATE_FORMAT(d.datetime, '%Y-%m-%d') TransDate,
						IF(d.total = 0, 'Non-taxed', 'Taxed') GroupLabel,
						COUNT(*) GroupQuantity,
						'transaction' GroupQuantityLabel,
						SUM(d.total) GroupValue
					FROM {$transarchive} d
					WHERE d.emp_no != 9999 AND (TRUE AND TRUE)
						AND d.trans_status != 'X'
						AND d.trans_type = 'A' AND d.upc = 'TAX'
						AND DATE_FORMAT(d.datetime, '%Y-%m-%d') = (SELECT MAX(DATE_FORMAT(datetime, '%Y-%m-%d')) FROM {$transarchive})
					GROUP BY (total = 0)
				",
		'discount' => "
					SELECT
						'discounts' Plural,
						DATE_FORMAT(d.datetime, '%Y-%m-%d') TransDate,
						CONCAT(d.percentDiscount, '%') GroupLabel,
						COUNT(*) GroupQuantity,
						'transaction' GroupQuantityLabel,
						-SUM(d.total) GroupValue
					FROM {$transarchive} d
					WHERE d.emp_no != 9999 AND (TRUE AND TRUE)
						AND d.trans_status != 'X'
						AND d.trans_type = 'S' AND d.upc = 'DISCOUNT'
						AND DATE_FORMAT(d.datetime, '%Y-%m-%d') = (SELECT MAX(DATE_FORMAT(datetime, '%Y-%m-%d')) FROM {$transarchive})
					GROUP BY percentDiscount
				",
		'tender' => "
					SELECT
						'tenders' Plural,
						DATE_FORMAT(d.datetime, '%Y-%m-%d') TransDate,
						t.TenderName GroupLabel,
						COUNT(*) GroupQuantity,
						'transaction' GroupQuantityLabel,
						-SUM(d.total) GroupValue
					FROM {$transarchive} d
						LEFT JOIN {$opdata}.tenders t ON d.trans_subtype = t.TenderCode
					WHERE d.emp_no != 9999 AND (TRUE AND TRUE)
						AND d.trans_status != 'X'
						AND d.trans_type = 'T'
						AND DATE_FORMAT(d.datetime, '%Y-%m-%d') = (SELECT MAX(DATE_FORMAT(datetime, '%Y-%m-%d')) FROM {$transarchive})
					GROUP BY t.tenderName
					ORDER BY
						FIELD(d.trans_subtype, 'CA', 'CK', 'CC', 'DC', 'EF', d.trans_subtype),
						d.trans_subtype
				",
		);

	foreach ($report_params as $report => $query) {
error_log("$report => $query");

		$receipt .= "\n";

		$receipt .= ReceiptLib::boldFont();
		$receipt .= ReceiptLib::centerString(ucwords($report).' Report')."\n";
		$receipt .= ReceiptLib::normalFont();

		$result = $office_db->query($query);
		if (!$result) $result = $lane_db->query($query);

		$total_quantity = $total_value = 0;
		$plural = $group_label = $group_quantity = $group_quantity_label = $group_value = '';

		while ($row = $office_db->fetchRow($result)) {
			$plural = $row['Plural'];
			$group_label = $row['GroupLabel'];
			$group_quantity = $row['GroupQuantity'];
			$group_quantity_label = $row['GroupQuantityLabel'];
			$group_value = $row['GroupValue'];

			$total_quantity += $group_quantity;
			$total_value += $group_value;

			$group_quantity = rtrim(number_format($group_quantity, 3), '.0');
			$group_value = number_format($group_value, 2);

			$receipt .= ReceiptLib::boldFont();
			$receipt .= "{$group_label}: ";
			$receipt .= ReceiptLib::normalFont();
			$receipt .= "\${$group_value} from {$group_quantity} {$group_quantity_label}".($group_quantity==1?'':'s')."\n";
		}
		switch ($report) {
			case 'department':
			case 'tax':
			case 'discount':
			case 'tender':
				$total_values[$report] = $total_value;
			default:
		}

		$total_quantity = rtrim(number_format($total_quantity, 3), '.0');
		$total_value = number_format($total_value, 2);

		$receipt .= ReceiptLib::boldFont();
		$receipt .= "All ".ucwords($plural).": \${$total_value} from {$total_quantity} {$group_quantity_label}".($total_quantity==1?'':'s')."\n";
		$receipt .= ReceiptLib::normalFont();
	}

	$checksum = 0;
	$receipt .= "\n";
	foreach ($total_values as $report => $total_value) {
		switch ($report) {
			case 'department':
			case 'tax':
				$sign = +1;
				break;
			case 'discount':
			case 'tender':
				$sign = -1;
				break;
			default:
				continue;
		}
		$checksum += ($sign * $total_value);
		$total_value = number_format($total_value, 2);

		$receipt .= "\n";
		$receipt .= str_repeat(' ', 8);
		$receipt .= ReceiptLib::boldFont();
		$receipt .= ucwords($report).' Total:';
		$receipt .= ReceiptLib::normalFont();
		$receipt .= str_repeat(' ', 32 - strlen("{$report}{$total_value}"));
		$receipt .= ($sign < 0? '-' : '+') . " \${$total_value}";
	}
	if ($sign) {
		$checksum = number_format($checksum, 2);
		if ($checksum === '-0.00') $checksum = '0.00'; // remove possible floating point sign error

		$receipt .= "\n";
		$receipt .= str_repeat(' ', 38);
		$receipt .= str_repeat('_', 14);
		$receipt .= "\n";
		$receipt .= str_repeat(' ', 8);
		$receipt .= ReceiptLib::boldFont();
		$receipt .= 'Checksum (should be zero):';
		$receipt .= str_repeat(' ', 15 - strlen("{$checksum}"));
		$receipt .= "\${$checksum}";
		$receipt .= ReceiptLib::normalFont();
	}

    $receipt .= "\n";
    $receipt .= "\n";
    $receipt .= ReceiptLib::centerString("------------------------------------------------------");
    $receipt .= "\n";

    $receipt .= str_repeat("\n", 4);
    $receipt .= chr(27).chr(105); // cut
    return $receipt;
}

}

