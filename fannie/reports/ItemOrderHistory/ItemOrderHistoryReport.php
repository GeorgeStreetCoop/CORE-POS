<?php
/*******************************************************************************

    Copyright 2013 Whole Foods Co-op

    This file is part of CORE-POS.

    CORE-POS is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    CORE-POS is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    in the file license.txt along with IT CORE; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

*********************************************************************************/

include(dirname(__FILE__) . '/../../config.php');
if (!class_exists('FannieAPI')) {
    include($FANNIE_ROOT.'classlib2.0/FannieAPI.php');
}

class ItemOrderHistoryReport extends FannieReportPage 
{
    public $description = '[Item Order History] shows purchase orders for a given item. Requires purchase orders or
    invoice information to be entered into POS.';
    public $themed = true;
    public $report_set = 'Purchasing';

    protected $title = "Fannie : Item Order History";
    protected $header = "Item Order History";

    protected $report_headers = array('Date', 'Vendor', 'Invoice#', 'SKU', '# Cases', 'Case Size', 'Unit Cost', 'Total');
    protected $required_fields = array('upc');
    protected $sort_direction = 1;

    public function report_description_content()
    {
        $dbc = $this->connection;
        $dbc->selectDB($this->config->get('OP_DB'));
        $prod = new ProductsModel($dbc);
        $prod->upc(BarcodeLib::padUPC($this->form->upc));
        $prod->load();
        $ret = array('Order History For ' . $prod->upc() . ' ' . $prod->description());
        if (FormLib::get('all')) {
            $ret[] = 'All [known] orders';
            if ($this->report_format = 'html') {
                $ret[] = sprintf('<a href="ItemOrderHistoryReport.php?upc=%s">Show Recent</a>', $prod->upc());
            }
        } else {
            $ret[] = 'Since ' . date('F d, Y', strtotime('92 days ago'));
            if ($this->report_format = 'html') {
                $ret[] = sprintf('<a href="ItemOrderHistoryReport.php?upc=%s&all=1">Show All</a>', $prod->upc());
            }
        }

        return $ret;
    }

    public function fetch_report_data()
    {
        $dbc = $this->connection;
        $dbc->selectDB($this->config->get('OP_DB'));

        $upc = $this->form->upc;
        $upc = BarcodeLib::padUPC($upc);

        $query = 'SELECT i.sku, i.quantity, i.unitCost, i.caseSize,
                        i.quantity * i.unitCost * i.caseSize AS ttl,
                        o.vendorInvoiceID, v.vendorName, o.placedDate
                        FROM PurchaseOrderItems AS i
                            LEFT JOIN PurchaseOrder AS o ON i.orderID=o.orderID
                            LEFT JOIN vendors AS v ON o.vendorID=v.vendorID
                        WHERE i.internalUPC = ?
                            AND o.placedDate >= ?
                        ORDER BY o.placedDate';
        $prep = $dbc->prepare($query);
        $args = array($upc);
        if (FormLib::get('all')) {
            $args[] = '1900-01-01 00:00:00';
        } else {
            $args[] = date('Y-m-d', strtotime('92 days ago'));
        }
        $result = $dbc->execute($prep, $args);
        $data = array();
        while ($row = $dbc->fetchRow($result)) {
            $data[] = $this->rowToRecord($row);
        }

        return $data;
    }

    private function rowToRecord($row)
    {
        return array(
            $row['placedDate'],
            $row['vendorName'],
            $row['vendorInvoiceID'],
            $row['sku'],
            $row['quantity'],
            $row['caseSize'],
            $row['unitCost'],
            $row['ttl'],
        );
    }

    public function form_content()
    {
        $this->add_onload_command('$(\'#upc\').focus();');
        return '
            <form action="' . $_SERVER['PHP_SELF'] . '" method="get">
            <div class="form-group form-inline">
                <label>UPC</label> 
                <input type=text name=upc id=upc class="form-control" />
                <button type=submit class="btn btn-default">Get Report</button>
            </div>
            </form>';
    }

    public function readinessCheck()
    {
        $dbc = $this->connection;
        $dbc->selectDB($this->config->get('OP_DB'));
        if ($this->tableExistsReadinessCheck($this->config->get('OP_DB'), 'PurchaseOrderItems') === false) {
            return false;
        } else {
            $testQ = 'SELECT orderID FROM PurchaseOrderItems';
            $testQ = $dbc->addSelectLimit($testQ, 1);
            $testR = $dbc->query($testQ);
            if ($dbc->num_rows($testR) == 0) {
                $this->error_text = _('No purchase orders have been entered.');
                return false;
            }
        }

        return true;
    }

    public function helpContent()
    {
        return '<p>
            Lists purchase orders and/or invoices
            containing a particular item.
            </p>';
    }

    public function unitTest($phpunit)
    {
        $data = array('placedDate'=>'2000-01-01', 'vendorName'=>'test',
            'vendorInvoiceID'=>'1234', 'sku'=>'111', 'quantity'=>1,
            'caseSize'=>5, 'unitCost'=>1, 'ttl'=>5);
        $phpunit->assertInternalType('array', $this->rowToRecord($data));
    }
}

FannieDispatch::conditionalExec();

