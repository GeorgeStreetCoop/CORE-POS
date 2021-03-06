<?php
/*******************************************************************************

    Copyright 2013 Whole Foods Co-op, Duluth, MN

    This file is part of CORE-POS.

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

if (!class_exists('FannieAPI')) {
    include_once(dirname(__FILE__).'/../../classlib2.0/FannieAPI.php');
}

class BaseItemModule extends ItemModule 
{

    private function getBarcodeType($upc)
    {
        $trimmed = ltrim($upc, '0');
        $barcode_type = '';
        if (strlen($trimmed) == '12') {
            // probably EAN-13 w/o check digi
            $barcode_type = 'EAN';
        } elseif (strlen($trimmed) == 11 && $trimmed[0] == '2') {
            // variable price UPC
            $barcode_type = 'Scale';
        } elseif (strlen($trimmed) <= 11 && strlen($trimmed) >= 6) {
            // probably UPC-A w/o check digit
            $barcode_type = 'UPC';
        } else {
            $barcode_type = 'PLU';
        }

        return $barcode_type;
    }

    private function getStores()
    {
        $store_model = new StoresModel($this->db());
        $store_model->hasOwnItems(1);
        $stores = array();
        foreach ($store_model->find('storeID') as $obj) {
            $stores[$obj->storeID()] = $obj;
        }

        return $stores;
    }

    private function prevNextItem($upc, $department)
    {
        /* find previous and next items in department */
        $dbc = $this->db();
        $prevP = $dbc->prepare('SELECT upc FROM products WHERE department=? AND upc < ? ORDER BY upc DESC');
        $nextP = $dbc->prepare('SELECT upc FROM products WHERE department=? AND upc > ? ORDER BY upc');
        $prevUPC = $dbc->getValue($prevP, array($department, $upc));
        $nextUPC = $dbc->getValue($nextP, array($department, $upc));

        return array($prevUPC, $nextUPC);
    }

    private function getVendorName($vendorID)
    {
        $dbc = $this->db();
        $prep = $dbc->prepare('SELECT vendorName FROM vendors WHERE vendorID=?');
        $name = $dbc->getValue($prep, array($vendorID));

        return ($name === false) ? '' : $name;
    }

    /**
      Look for items with a similar UPC to guess what
      department this item goes in. If found, use 
      department settings to fill in some defaults
    */
    private function guessDepartment($upc)
    {
        $dbc = $this->db();
        $search = substr($upc,0,12);
        $searchP = $dbc->prepare('SELECT department FROM products WHERE upc LIKE ?');
        $department = 0;
        while (strlen($search) >= 8) {
            $searchR = $dbc->execute($searchP,array($search.'%'));
            if ($dbc->numRows($searchR) > 0) {
                $searchW = $dbc->fetchRow($searchR);
                $department = $searchW['department'];
                break;
            }
            $search = substr($search,0,strlen($search)-1);
        }

        return $department;
    }

    public function showEditForm($upc, $display_mode=1, $expand_mode=1)
    {
        $FANNIE_PRODUCT_MODULES = FannieConfig::config('PRODUCT_MODULES', array());
        $upc = BarcodeLib::padUPC($upc);
        $barcode_type = $this->getBarcodeType($upc);

        $ret = '<div id="BaseItemFieldset" class="panel panel-default">';

        $dbc = $this->db();
        $q = '
            SELECT
                p.description,
                p.pricemethod,
                p.normal_price,
                p.cost,
                CASE 
                    WHEN p.size IS NULL OR p.size=\'\' OR p.size=\'0\' AND v.size IS NOT NULL THEN v.size 
                    ELSE p.size 
                END AS size,
                p.unitofmeasure,
                p.modified,
                p.last_sold,
                p.special_price,
                p.end_date,
                p.subdept,
                p.department,
                p.tax,
                p.foodstamp,
                p.scale,
                p.qttyEnforced,
                p.discount,
                p.line_item_discountable,
                p.brand AS manufacturer,
                x.distributor,
                u.description as ldesc,
                p.default_vendor_id,
                v.units AS caseSize,
                v.sku,
                p.inUse,
                p.idEnforced,
                p.local,
                p.deposit,
                p.discounttype,
                p.wicable,
                p.store_id
            FROM products AS p 
                LEFT JOIN prodExtra AS x ON p.upc=x.upc 
                LEFT JOIN productUser AS u ON p.upc=u.upc 
                LEFT JOIN vendorItems AS v ON p.upc=v.upc AND p.default_vendor_id = v.vendorID
            WHERE p.upc=?';
        $p_def = $dbc->tableDefinition('products');
        if (!isset($p_def['last_sold'])) {
            $q = str_replace('p.last_sold', 'NULL as last_sold', $q);
        }
        $p = $dbc->prepare($q);
        $r = $dbc->execute($p,array($upc));
        $stores = $this->getStores();
        $items = array();
        $rowItem = array();
        $prevUPC = false;
        $nextUPC = false;
        $likeCode = false;
        if ($dbc->num_rows($r) > 0) {
            //existing item
            while ($w = $dbc->fetch_row($r)) {
                $items[$w['store_id']] = $w;
                $rowItem = $w;
            }

            $rowItem['distributor'] = $this->getVendorName($rowItem['default_vendor_id']);

            /* find previous and next items in department */
            list($prevUPC, $nextUPC) = $this->prevNextItem($rowItem['department'], $upc);

            $lcP = $dbc->prepare('SELECT likeCode FROM upcLike WHERE upc=?');
            $lcR = $dbc->execute($lcP,array($upc));
            if ($dbc->num_rows($lcR) > 0) {
                $lcW = $dbc->fetch_row($lcR);
                $likeCode = $lcW['likeCode'];
            }

            if (FannieConfig::config('STORE_MODE') == 'HQ') {
                $default_id = array_keys($items);
                $default_id = $default_id[0];
                $default_item = $items[$default_id];
                foreach ($stores as $id => $info) {
                    if (!isset($items[$id])) {
                        $items[$id] = $default_item;
                    }
                }
            }
        } else {
            // default values for form fields
            $rowItem = array(
                'description' => '',
                'normal_price' => 0,
                'pricemethod' => 0,
                'size' => '',
                'unitofmeasure' => '',
                'modified' => '',
                'ledesc' => '',
                'manufacturer' => '',
                'distributor' => '',
                'default_vendor_id' => 0,
                'department' => 0,
                'subdept' => 0,
                'tax' => 0,
                'foodstamp' => 0,
                'scale' => 0,
                'qttyEnforced' => 0,
                'discount' => 1,
                'line_item_discountable' => 1,
                'caseSize' => '',
                'sku' => '',
                'inUse' => 1,
                'idEnforced' => 0,
                'local' => 0,
                'deposit' => 0,
                'cost' => 0,
                'discounttype' => 0,
                'wicable' => 0,
            );

            /**
              Check for entries in the vendorItems table to prepopulate
              fields for the new item
            */
            $vendorP = "
                SELECT 
                    i.description,
                    i.brand as manufacturer,
                    i.cost,
                    v.vendorName as distributor,
                    d.margin,
                    i.vendorID,
                    i.srp,
                    i.size,
                    i.units,
                    i.sku,
                    i.vendorID as default_vendor_id
                FROM vendorItems AS i 
                    LEFT JOIN vendors AS v ON i.vendorID=v.vendorID
                    LEFT JOIN vendorDepartments AS d ON i.vendorDept=d.deptID AND d.vendorID=i.vendorID
                WHERE i.upc=?";
            $args = array($upc);
            $vID = FormLib::get_form_value('vid','');
            if ($vID !== ''){
                $vendorP .= ' AND i.vendorID=?';
                $args[] = $vID;
            }
            $vendorP .= ' ORDER BY i.vendorID';
            $vendorP = $dbc->prepare($vendorP);
            $vendorR = $dbc->execute($vendorP,$args);
            
            if ($dbc->num_rows($vendorR) > 0){
                $v = $dbc->fetch_row($vendorR);
                $ret .= "<div><i>This product is in the ".$v['distributor']." catalog. Values have
                    been filled in where possible</i></div>";
                $rowItem['description'] = $v['description'];
                $rowItem['manufacturer'] = $v['manufacturer'];
                $rowItem['cost'] = $v['cost'];
                $rowItem['distributor'] = $v['distributor'];
                $rowItem['normal_price'] = $v['srp'];
                $rowItem['default_vendor_id'] = $v['vendorID'];
                $rowItem['size'] = $v['size'];
                $rowItem['caseSize'] = $v['units'];
                $rowItem['sku'] = $v['sku'];

                while ($v = $dbc->fetch_row($vendorR)) {
                    $ret .= sprintf('This product is also in <a href="?searchupc=%s&vid=%d">%s</a><br />',
                        $upc,$v['vendorID'],$v['distributor']);
                }
            }

            $rowItem['department'] = $this->guessDepartment($upc);
            /**
              If no match is found, pick the most
              commonly used department
            */
            if ($rowItem['department'] == 0) {
                $commonP = $dbc->prepare('
                    SELECT department,
                        COUNT(*)
                    FROM products
                    GROUP BY department
                    ORDER BY COUNT(*) DESC');
                $rowItem['department'] = $dbc->getValue($commonP);
            }
            /**
              Get defaults for chosen department
            */
            $dmodel = new DepartmentsModel($dbc);
            $dmodel->dept_no($rowItem['department']);
            if ($dmodel->load()) {
                $rowItem['tax'] = $dmodel->dept_tax();
                $rowItem['foodstamp'] = $dmodel->dept_fs();
                $rowItem['discount'] = $dmodel->dept_discount();
                $rowItem['line_item_discountable'] = $dmodel->line_item_discount();
            }

            foreach ($stores as $id => $obj) {
                $items[$id] = $rowItem;
            }
        }

        $ret .= '<div class="panel-heading">';
        if ($prevUPC) {
            $ret .= ' <a class="btn btn-default btn-xs small" href="ItemEditorPage.php?searchupc=' . $prevUPC . '"
                title="Previous item in this department">
                <span class="glyphicon glyphicon-chevron-left"></span></a> ';
        }
        $ret .= '<strong>UPC</strong>
                <span class="text-danger">';
        switch ($barcode_type) {
            case 'EAN':
            case 'UPC':
                $ret .= substr($upc, 0, 3) 
                    . '<a class="text-danger iframe fancyboxLink" href="../reports/ProductLine/ProductLineReport.php?prefix='
                    . substr($upc, 3, 5) . '" title="Product Line">'
                    . '<strong>' . substr($upc, 3, 5) . '</strong>'
                    . '</a>'
                    . substr($upc, 8);
                break;
            case 'Scale':
                $ret .= substr($upc, 0, 3)
                    . '<strong>' . substr($upc, 3, 4) . '</strong>'
                    . substr($upc, 7);
                break;
            case 'PLU':
                $trimmed = ltrim($upc, '0');
                if (strlen($trimmed) < 13) {
                    $ret .= str_repeat('0', 13-strlen($trimmed))
                        . '<strong>' . $trimmed . '</strong>';
                } else {
                    $ret .= $upc;
                }
                break;
            default:
                $ret .= $upc;
        }
        $ret .= '</span>';
        $ret .= '<input type="hidden" id="upc" name="upc" value="' . $upc . '" />';
        if ($nextUPC) {
            $ret .= ' <a class="btn btn-default btn-xs small" href="ItemEditorPage.php?searchupc=' . $nextUPC . '"
                title="Next item in this department">
                <span class="glyphicon glyphicon-chevron-right"></span></a>';
        }
        $ret .= ' <label style="color:darkmagenta;">Modified</label>
                <span style="color:darkmagenta;">'. $rowItem['modified'] . '</span>';
        $ret .= ' | <label style="color:darkmagenta;">Last Sold</label>
                <span style="color:darkmagenta;">'. (empty($rowItem['last_sold']) ? 'n/a' : $rowItem['last_sold']) . '</span>';
        $ret .= '</div>'; // end panel-heading

        $ret .= '<div class="panel-body">';

        $new_item = false;
        if ($dbc->num_rows($r) == 0) {
            // new item
            $ret .= "<div class=\"alert alert-warning\">Item not found.  You are creating a new one.</div>";
            $new_item = true;
        }

        $nav_tabs = '<ul id="store-tabs" class="nav nav-tabs small" role="tablist">';
        $ret .= '{{nav_tabs}}<div class="tab-content">';
        $active_tab = true;
        foreach ($items as $store_id => $rowItem) {
            $tabID = 'store-tab-' . $store_id;
            $store_description = 'n/a';
            if (isset($stores[$store_id])) {
                $store_description = $stores[$store_id]->description();
            }
            $nav_tabs .= '<li role="presentation" ' . ($active_tab ? 'class="active"' : '') . '>'
                . '<a href="#' . $tabID . '" aria-controls="' . $tabID . '" '
                . 'onclick="$(\'.tab-content .chosen-select:visible\').chosen();"'
                . 'role="tab" data-toggle="tab">'
                . $store_description . '</a></li>';
            $ret .= '<div role="tabpanel" class="tab-pane' . ($active_tab ? ' active' : '') . '"
                id="' . $tabID . '">';

            $ret .= '<input type="hidden" class="store-id" name="store_id[]" value="' . $store_id . '" />';
            $ret .= '<table class="table table-bordered">';

            $limit = 30 - strlen(isset($rowItem['description'])?$rowItem['description']:'');
            $ret .= <<<HTML
<tr>
    <th class="text-right">Description</th>
    <td colspan="5">
        <div class="input-group" style="width:100%;">
            <input type="text" maxlength="30" class="form-control syncable-input" required
                name="descript[]" id="descript" value="{{description}}"
                onkeyup="$(this).next().html(30-(this.value.length));" />
            <span class="input-group-addon">{{limit}}</span>
        </div>
    </td>
    <th class="text-right">Cost</th>
    <td>
        <div class="input-group">
            <span class="input-group-addon">$</span>
            <input type="text" id="cost{{store_id}}" name="cost[]" 
                class="form-control price-field cost-input syncable-input"
                value="{{cost}}" data-store-id="{{store_id}}" maxlength="6"
                onkeydown="if (typeof nosubmit == 'function') nosubmit(event);"
                onkeyup="if (typeof nosubmit == 'function') nosubmit(event);" 
                onchange="$('.default_vendor_cost').val(this.value);"
            />
        </div>
    </td>
    <th class="text-right">Price</th>
    <td>
        <div class="input-group">
            <span class="input-group-addon">$</span>
            <input type="text" id="price{{store_id}}" name="price[]" 
                class="form-control price-field price-input syncable-input"
                data-store-id="{{store_id}}" maxlength="6"
                required value="{{normal_price}}" />
        </div>
    </td>
</tr>
HTML;
            $ret = str_replace('{{description}}', $rowItem['description'], $ret);
            $ret = str_replace('{{limit}}', $limit, $ret);
            $ret = str_replace('{{cost}}', sprintf('%.2f', $rowItem['cost']), $ret);
            $ret = str_replace('{{normal_price}}', sprintf('%.2f', $rowItem['normal_price']), $ret);

            // no need to display this field twice
            if (!isset($FANNIE_PRODUCT_MODULES['ProdUserModule'])) {
                $ret .= '
                    <tr>
                        <th>Long Desc.</th>
                        <td colspan="5">
                        <input type="text" size="60" name="puser_description" maxlength="255"
                            ' . (!$active_tab ? ' disabled ' : '') . '
                            value="' . $rowItem['ldesc'] . '" class="form-control" />
                        </td>
                    </tr>';
            }

            $ret .= '
                <tr>
                    <th class="text-right">Brand</th>
                    <td colspan="5">
                        <input type="text" name="manufacturer[]" 
                            class="form-control input-sm brand-field syncable-input"
                            value="' . $rowItem['manufacturer'] . '" />
                    </td>';
            /**
              Check products.default_vendor_id to see if it is a 
              valid reference to the vendors table
            */
            $normalizedVendorID = false;
            if (isset($rowItem['default_vendor_id']) && $rowItem['default_vendor_id'] <> 0) {
                $normalizedVendorID = $rowItem['default_vendor_id'];
            }
            /**
              Use a <select> box if the current vendor corresponds to a valid
              entry OR if no vendor entry exists. Only allow free text
              if it's already in place
            */
            $ret .= ' <th class="text-right">Vendor</th> ';
            if ($normalizedVendorID || empty($rowItem['distributor'])) {
                $ret .= '<td colspan="3" class="form-inline"><select name="distributor[]" 
                            class="chosen-select form-control vendor_field syncable-input"
                            onchange="vendorChanged(this.value);">';
                $ret .= '<option value="0">Select a vendor</option>';
                $vendR = $dbc->query('SELECT vendorID, vendorName FROM vendors ORDER BY vendorName');
                while ($vendW = $dbc->fetchRow($vendR)) {
                    $ret .= sprintf('<option %s>%s</option>',
                                ($vendW['vendorID'] == $normalizedVendorID ? 'selected' : ''),
                                $vendW['vendorName']);
                }
                $ret .= '</select>';
            } else {
                $ret .= "<td colspan=\"3\"><input type=text name=distributor[] size=8 value=\""
                    .(isset($rowItem['distributor'])?$rowItem['distributor']:"")
                    ."\" class=\"form-control vendor-field syncable-input\" />";
            }
            $ret .= ' <button type="button" 
                        title="Create new vendor"
                        class="btn btn-default btn-sm newVendorButton">
                        <span class="glyphicon glyphicon-plus"></span></button>';
            $ret .= '</td></tr>'; // end row

            if (isset($rowItem['discounttype']) && $rowItem['discounttype'] <> 0) {
                /* show sale info */
                $batchP = $dbc->prepare("
                    SELECT b.batchName, 
                        b.batchID 
                    FROM batches AS b 
                        LEFT JOIN batchList as l on b.batchID=l.batchID 
                    WHERE '" . date('Y-m-d') . "' BETWEEN b.startDate AND b.endDate 
                        AND (l.upc=? OR l.upc=?)"
                );
                $batchR = $dbc->execute($batchP,array($upc,'LC'.$likeCode));
                $batch = array('batchID'=>0, 'batchName'=>"Unknown");
                if ($dbc->num_rows($batchR) > 0) {
                    $batch = $dbc->fetch_row($batchR);
                }

                $ret .= '<td class="alert-success" colspan="8">';
                $ret .= sprintf("<strong>Sale Price:</strong>
                    %.2f (<em>Batch: <a href=\"%sbatches/newbatch/EditBatchPage.php?id=%d\">%s</a></em>)",
                    $rowItem['special_price'], FannieConfig::config('URL'), $batch['batchID'], $batch['batchName']);
                list($date,$time) = explode(' ',$rowItem['end_date']);
                $ret .= "<strong>End Date:</strong>
                        $date 
                        (<a href=\"EndItemSale.php?id=$upc\">Unsale Now</a>)";
                $ret .= '</td>';
            }

            $supers = array();
            $depts = array();
            $subs = array();
            $range_limit = FannieAuth::validateUserLimited('pricechange');
            $deptQ = '
                SELECT dept_no,
                    dept_name,
                    subdept_no,
                    subdept_name,
                    s.dept_ID,
                    MIN(m.superID) AS superID
                FROM departments AS d
                    LEFT JOIN subdepts AS s ON d.dept_no=s.dept_ID
                    LEFT JOIN superdepts AS m ON d.dept_no=m.dept_ID ';
            if (is_array($range_limit) && count($range_limit) == 2) {
                $deptQ .= ' WHERE m.superID BETWEEN ? AND ? ';
            } else {
                $range_limit = array();
            }
            $deptQ .= '
                GROUP BY d.dept_no,
                    d.dept_name,
                    s.subdept_no,
                    s.subdept_name,
                s.dept_ID
                ORDER BY d.dept_no, s.subdept_name';
            $p = $dbc->prepare($deptQ);
            $r = $dbc->execute($p, $range_limit);
            $superID = '';
            while ($w = $dbc->fetch_row($r)) {
                if (!isset($depts[$w['dept_no']])) $depts[$w['dept_no']] = $w['dept_name'];
                if ($w['dept_no'] == $rowItem['department']) {
                    $superID = $w['superID'];
                }
                if (!isset($supers[$w['superID']])) {
                    $supers[$w['superID']] = array();
                }
                $supers[$w['superID']][] = $w['dept_no'];

                if ($w['subdept_no'] == '') {
                    continue;
                }

                if (!isset($subs[$w['dept_ID']]))
                    $subs[$w['dept_ID']] = '';
                $subs[$w['dept_ID']] .= sprintf('<option %s value="%d">%d %s</option>',
                        ($w['subdept_no'] == $rowItem['subdept'] ? 'selected':''),
                        $w['subdept_no'],$w['subdept_no'],$w['subdept_name']);
            }

            $ret .= '<tr>
                <th class="text-right">Dept</th>
                <td colspan="7" class="form-inline">
                <select id="super-dept{{store_id}}" name="super[]"
                    class="form-control chosen-select syncable-input" 
                    onchange="chainSuperDepartment(\'../ws/\', this.value, {dept_start:\'#department{{store_id}}\', callback:function(){$(\'#department{{store_id}}\').trigger(\'chosen:updated\');baseItemChainSubs({{store_id}});}});">';
            $names = new SuperDeptNamesModel($dbc);
            $superQ = 'SELECT superID, super_name FROM superDeptNames';
            $superArgs = array();
            if (is_array($range_limit) && count($range_limit) == 2) {
                $superArgs = $range_limit;
                $superQ .= ' WHERE superID BETWEEN ? AND ? ';
            }
            $superQ .= ' ORDER BY superID';
            $superP = $dbc->prepare($superQ);
            $superR = $dbc->execute($superP, $superArgs);
            while ($superW = $dbc->fetchRow($superR)) {
                $ret .= sprintf('<option %s value="%d">%s</option>',
                        $superW['superID'] == $superID ? 'selected' : '',
                        $superW['superID'], $superW['super_name']);
            }
            $ret .= '</select>
                <select name="department[]" id="department{{store_id}}" 
                    class="form-control chosen-select syncable-input" 
                    onchange="baseItemChainSubs({{store_id}});">';
            foreach ($depts as $id => $name){
                if (is_numeric($superID) && is_array($supers[$superID])) {
                    if (!in_array($id, $supers[$superID]) && $id != $rowItem['department']) {
                        continue;
                    }
                }
                $ret .= sprintf('<option %s value="%d">%d %s</option>',
                        ($id == $rowItem['department'] ? 'selected':''),
                        $id,$id,$name);
            }
            $ret .= '</select>';
            $jsVendorID = $rowItem['default_vendor_id'] > 0 ? $rowItem['default_vendor_id'] : 'no-vendor';
            $ret .= '<select name="subdept[]" id="subdept{{store_id}}" 
                class="form-control chosen-select syncable-input">';
            $ret .= isset($subs[$rowItem['department']]) ? $subs[$rowItem['department']] : '<option value="0">None</option>';
            $ret .= '</select>';
            $ret .= '</td>
                <th class="small text-right">SKU</th>
                <td colspan="2">
                    <input type="text" name="vendorSKU" class="form-control input-sm"
                        value="' . $rowItem['sku'] . '" 
                        onchange="$(\'#vsku' . $jsVendorID . '\').val(this.value);" 
                        ' . ($jsVendorID == 'no-vendor' || !$active_tab ? 'disabled' : '') . '
                        id="product-sku-field" />
                </td>
                </tr>';

            $taxQ = $dbc->prepare('SELECT id,description FROM taxrates ORDER BY id');
            $taxR = $dbc->execute($taxQ);
            $rates = array();
            while ($taxW = $dbc->fetch_row($taxR)) {
                array_push($rates,array($taxW[0],$taxW[1]));
            }
            array_push($rates,array("0","NoTax"));
            $ret .= '<tr>
                <th class="small text-right">Tax</th>
                <td>
                <select name="tax[]" id="tax{{store_id}}" 
                    class="form-control input-sm syncable-input">';
            foreach($rates as $r){
                $ret .= sprintf('<option %s value="%d">%s</option>',
                    (isset($rowItem['tax'])&&$rowItem['tax']==$r[0]?'selected':''),
                    $r[0],$r[1]);
            }
            $ret .= '</select></td>';

            $ret .= '<td colspan="4" class="small">
                <label>FS
                <input type="checkbox" value="{{store_id}}" name="FS[]" id="FS{{store_id}}"
                    class="syncable-checkbox"
                    ' . ($rowItem['foodstamp'] == 1 ? 'checked' : '') . ' />
                </label>
                &nbsp;&nbsp;&nbsp;&nbsp;
                <label>Scale
                <input type="checkbox" value="{{store_id}}" name="Scale[]" 
                    class="scale-checkbox syncable-checkbox"
                    ' . ($rowItem['scale'] == 1 ? 'checked' : '') . ' />
                </label>
                &nbsp;&nbsp;&nbsp;&nbsp;
                <label>QtyFrc
                <input type="checkbox" value="{{store_id}}" name="QtyFrc[]" 
                    class="qty-checkbox syncable-checkbox"
                    ' . ($rowItem['qttyEnforced'] == 1 ? 'checked' : '') . ' />
                </label>
                &nbsp;&nbsp;&nbsp;&nbsp;
                <label>WIC
                <input type="checkbox" value="{{store_id}}" name="prod-wicable[]" 
                    class="prod-wicable-checkbox syncable-checkbox"
                    ' . ($rowItem['wicable'] == 1 ? 'checked' : '') . '  />
                </label>
                &nbsp;&nbsp;&nbsp;&nbsp;
                <label>InUse
                <input type="checkbox" value="{{store_id}}" name="prod-in-use[]" 
                    class="in-use-checkbox syncable-checkbox"
                    ' . ($rowItem['inUse'] == 1 ? 'checked' : '') . ' 
                    onchange="$(\'#extra-in-use-checkbox\').prop(\'checked\', $(this).prop(\'checked\'));" />
                </label>
                </td>
                <th class="small text-right">Discount</th>
                <td class="col-sm-1">
                <select id="discount-select{{store_id}}" name="discount[]" 
                    class="form-control input-sm syncable-input">';
            $disc_opts = array(
                0 => 'No',
                1 => 'Yes',
                2 => 'Trans Only',
                3 => 'Line Only',
            );
            if ($rowItem['discount'] == 1 && $rowItem['line_item_discountable'] == 1) {
                $rowItem['discount'] = 1;
            } elseif ($rowItem['discount'] == 1 && $rowItem['line_item_discountable'] == 0) {
                $rowItem['discount'] = 2;
            } elseif ($rowItem['discount'] == 0 && $rowItem['line_item_discountable'] == 1) {
                $rowItem['discount'] = 3;
            } 
            foreach ($disc_opts as $id => $val) {
                $ret .= sprintf('<option %s value="%d">%s</option>',
                            ($id == $rowItem['discount'] ? 'selected' : ''),
                            $id, $val);
            }
            $ret .= '</select></td>
                <th class="small text-right">Deposit</th>
                <td colspan="2">
                    <input type="text" name="deposit-upc[]" class="form-control input-sm syncable-input"
                        value="' . ($rowItem['deposit'] != 0 ? $rowItem['deposit'] : '') . '" 
                        placeholder="Deposit Item PLU/UPC"
                        onchange="$(\'#deposit\').val(this.value);" />
                </td>
                </tr>';

            $ret .= '
                <tr>
                    <th class="small text-right">Case Size</th>
                    <td class="col-sm-1">
                        <input type="text" name="caseSize" class="form-control input-sm"
                            id="product-case-size"
                            value="' . $rowItem['caseSize'] . '" 
                            onchange="$(\'#vunits' . $jsVendorID . '\').val(this.value);" 
                            ' . ($jsVendorID == 'no-vendor' || !$active_tab ? 'disabled' : '') . ' />
                    </td>
                    <th class="small text-right">Pack Size</th>
                    <td class="col-sm-1">
                        <input type="text" name="size[]" 
                            class="form-control input-sm product-pack-size syncable-input"
                            value="' . $rowItem['size'] . '" 
                            onchange="$(\'#vsize' . $jsVendorID . '\').val(this.value);" />
                    </td>
                    <th class="small text-right">Unit of measure</th>
                    <td class="col-sm-1">
                        <input type="text" name="unitm[]" 
                            class="form-control input-sm unit-of-measure syncable-input"
                            value="' . $rowItem['unitofmeasure'] . '" />
                    </td>
                    <th class="small text-right">Age Req</th>
                    <td class="col-sm-1">
                        <select name="id-enforced[]" class="form-control input-sm id-enforced syncable-input"
                            onchange="$(\'#idReq\').val(this.value);">';
            $ages = array('n/a'=>0, 18=>18, 21=>21);
            foreach($ages as $label => $age) {
                $ret .= sprintf('<option %s value="%d">%s</option>',
                                ($age == $rowItem['idEnforced'] ? 'selected' : ''),
                                $age, $label);
            }
            $ret .= '</select>
                </td>
                <th class="small text-right">Local</th>
                <td>
                    <select name="prod-local[]" class="form-control input-sm prod-local syncable-input"
                        onchange="$(\'#local-origin-id\').val(this.value);">';
            $local_opts = array(0=>'No');
            $origin = new OriginsModel($dbc);
            $local_opts = array_merge($local_opts, $origin->getLocalOrigins());
            if (count($local_opts) == 1) {
                $local_opts[1] = 'Yes'; // generic local if no origins defined
            }
            foreach($local_opts as $id => $val) {
                $ret .= sprintf('<option value="%d" %s>%s</option>',
                    $id, ($id == $rowItem['local']?'selected':''), $val);
            }
            $ret .= '</select>
                    </td>
                    </tr>
                </div>';
            $ret .= '</table>';
            $ret .= '</div>';

            $ret = str_replace('{{store_id}}', $store_id, $ret);
            $active_tab = false;
            if (FannieConfig::config('STORE_MODE') != 'HQ') {
                break;
            }
        }
        $ret .= '</div>';
        // sync button will copy current tab values to all other store tabs
        if (!$new_item && FannieConfig::config('STORE_MODE') == 'HQ') {
            $nav_tabs .= '<li><label title="Apply update to all stores">
                <input type="checkbox" id="store-sync" checked /> Sync</label></li>';
        }
        $nav_tabs .= '</ul>';
        // only show the store tabs in HQ mode
        if (FannieConfig::config('STORE_MODE') == 'HQ') {
            $ret = str_replace('{{nav_tabs}}', $nav_tabs, $ret);
        } else {
            $ret = str_replace('{{nav_tabs}}', '', $ret);
        }

        $ret .= <<<HTML
<div id="newVendorDialog" title="Create new Vendor" class="collapse">
    <fieldset>
        <label for="newVendorName">Vendor Name</label>
        <input type="text" name="newVendorName" id="newVendorName" class="form-control" />
    </fieldset>
</div>
HTML;
        $ret .= '</div>'; // end panel-body
        $ret .= '</div>'; // end panel

        return $ret;
    }

    public function getFormJavascript($upc)
    {
        $FANNIE_URL = FannieConfig::config('URL');
        ob_start();
        ?>
        function baseItemChainSubs(store_id)
        {
            chainSubDepartments(
                '../ws/',
                {
                    super_id: '#super-dept'+store_id,
                    dept_start: '#department'+store_id,
                    dept_end: '#department'+store_id, 
                    sub_start: '#subdept'+store_id,
                    callback: function() {
                        $('#subdept'+store_id+' option:first').html('None').val(0);
                        $('#subdept'+store_id).trigger('chosen:updated');
                        $.ajax({
                            url: 'modules/BaseItemModule.php',
                            data: 'dept_defaults='+$('#department'+store_id).val(),
                            dataType: 'json',
                            cache: false,
                            success: function(data){
                                if (data.tax)
                                    $('#tax'+store_id).val(data.tax);
                                if (data.fs)
                                    $('#FS'+store_id).prop('checked',true);
                                else{
                                    $('#FS'+store_id).prop('checked', false);
                                }
                                if (data.nodisc && !data.line) {
                                    $('#discount-select'+store_id).val(0);
                                } else if (!data.nodisc && data.line) {
                                    $('#discount-select'+store_id).val(1);
                                } else if (!data.nodisc && !data.line) {
                                    $('#discount-select'+store_id).val(2);
                                } else {
                                    $('#discount-select'+store_id).val(3);
                                }
                            }
                        });
                    }
                }
            );
        }
        function vendorChanged(newVal)
        {
            $.ajax({
                url: '<?php echo $FANNIE_URL; ?>item/modules/BaseItemModule.php',
                data: 'vendorChanged='+newVal,
                dataType: 'json',
                cache: false,
                success: function(resp) {
                    if (!resp.error) {
                        $('#local-origin-id').val(resp.localID);
                        $('.product-case-size').prop('disabled', false);
                        $('#product-sku-field').prop('disabled', false);
                    } else {
                        $('.product-case-size').prop('disabled', true);
                        $('#product-sku-field').prop('disabled', true);
                    }
                }
            });
        }
        function addVendorDialog()
        {
            var v_dialog = $('#newVendorDialog').dialog({
                autoOpen: false,
                height: 300,
                width: 300,
                modal: true,
                buttons: {
                    "Create Vendor" : addVendorCallback,
                    "Cancel" : function() {
                        v_dialog.dialog("close");
                    }
                },
                close: function() {
                    $('#newVendorDialog :input').each(function(){
                        $(this).val('');
                    });
                    $('#newVendorAlert').html('');
                }
            });

            $('#newVendorDialog :input').keyup(function(e) {
                if (e.which == 13) {
                    addVendorCallback();
                }
            });

            $('.newVendorButton').click(function(e){
                e.preventDefault();
                v_dialog.dialog("open"); 
            });

            function addVendorCallback()
            {
                var data = 'action=addVendor';
                data += '&' + $('#newVendorDialog :input').serialize();
                $.ajax({
                    url: '<?php echo $FANNIE_URL; ?>item/modules/BaseItemModule.php',
                    data: data,
                    dataType: 'json',
                    error: function() {
                        $('#newVendorAlert').html('Communication error');
                    },
                    success: function(resp){
                        if (resp.vendorID) {
                            v_dialog.dialog("close");
                            $('.vendor_field').each(function(){
                                var v_field = $(this);
                                if (v_field.hasClass('chosen-select')) {
                                    var newopt = $('<option/>').attr('id', resp.vendorID).html(resp.vendorName);
                                    v_field.append(newopt);
                                }
                                v_field.val(resp.vendorName);
                                if (v_field.hasClass('chosen-select')) {
                                    v_field.trigger('chosen:updated');
                                }
                            });
                        } else if (resp.error) {
                            $('#newVendorAlert').html(resp.error);
                        } else {
                            $('#newVendorAlert').html('Invalid response');
                        }
                    }
                });
            }

        }
        function syncStoreTabs()
        {
            if ($('#store-sync').prop('checked') === false) {
                markUnSynced();
                return true;
            }
            var store_id = $('.tab-pane.active .store-id:first').val();
            var current = {};
            $('#store-tab-'+store_id+' .syncable-input').each(function(){
                if ($(this).attr('name').length > 0) {
                    var name = $(this).attr('name');
                    var val = $(this).val();
                    current[name] = val;
                }
            });
            $('.syncable-input').each(function(){
                if ($(this).attr('name').length > 0) {
                    var name = $(this).attr('name');
                    if (name in current) {
                        $(this).val(current[name]);
                        if ($(this).hasClass('chosen-select')) {
                            $(this).trigger('chosen:updated');
                        }
                    }
                }
            });
            var checkboxes = {};
            $('#store-tab-'+store_id+' .syncable-checkbox').each(function(){
                if ($(this).attr('name').length > 0) {
                    var name = $(this).attr('name');
                    if ($(this).prop('checked')) {
                        checkboxes[name] = true;
                    } else {
                        checkboxes[name] = false;
                    }
                }
            });
            $('.syncable-checkbox').each(function(){
                if ($(this).attr('name').length > 0) {
                    var name = $(this).attr('name');
                    if (name in checkboxes) {
                        $(this).prop('checked', checkboxes[name]);
                    }
                }
            });

            return true;
        }

        function markUnSynced()
        {
            var store_id = $('.tab-pane.active .store-id:first').val();
            var current = {};
            $('#store-tab-'+store_id+' .syncable-input').each(function(){
                if ($(this).attr('name').length > 0) {
                    var name = $(this).attr('name');
                    var val = $(this).val();
                    current[name] = val;
                }
            });
            var synced = {};
            $('.syncable-input').each(function(){
                if ($(this).attr('name').length > 0) {
                    var name = $(this).attr('name');
                    if (name in current && $(this).val() != current[name]) {
                        synced[name] = false;
                        $('#store-sync').prop('checked', false);
                    } else {
                        synced[name] = true;
                    }
                }
            });
            $('.syncable-input').each(function() {
                if ($(this).attr('name').length > 0) {
                    var name = $(this).attr('name');
                    if (name in synced && synced[name] === false) {
                        $(this).addClass('alert-warning');
                    } else {
                        $(this).removeClass('alert-warning');
                    }
                }
            });
            var checkboxes = {};
            $('#store-tab-'+store_id+' .syncable-checkbox').each(function(){
                if ($(this).attr('name').length > 0) {
                    var name = $(this).attr('name');
                    if ($(this).prop('checked')) {
                        checkboxes[name] = true;
                    } else {
                        checkboxes[name] = false;
                    }
                }
            });
            var synced = {};
            $('.syncable-checkbox').each(function(){
                if ($(this).attr('name').length > 0) {
                    var name = $(this).attr('name');
                    if (name in checkboxes && $(this).prop('checked') != checkboxes[name]) {
                        synced[name] = false;
                        $('#store-sync').prop('checked', false);
                    } else {
                        synced[name] = true;
                    }
                }
            });
            $('.syncable-checkbox').each(function(){
                if ($(this).attr('name').length > 0) {
                    var name = $(this).attr('name');
                    if (name in synced && synced[name] === false) {
                        $(this).closest('label').addClass('alert-warning');
                    } else {
                        $(this).closest('label').removeClass('alert-warning');
                    }
                }
            });
        }
        <?php

        return ob_get_clean();
    }

    private function formNoEx($field, $default)
    {
        try {
            return $this->form->{$field};
        } catch (Exception $ex) {
            return $default;
        }
    }

    function SaveFormData($upc)
    {
        $FANNIE_PRODUCT_MODULES = FannieConfig::config('PRODUCT_MODULES', array());
        $upc = BarcodeLib::padUPC($upc);
        $dbc = $this->db();

        $model = new ProductsModel($dbc);
        $model->upc($upc);
        if (!$model->load()) {
            // fully init new record
            $model->special_price(0);
            $model->specialpricemethod(0);
            $model->specialquantity(0);
            $model->specialgroupprice(0);
            $model->advertised(0);
            $model->tareweight(0);
            $model->start_date('0000-00-00');
            $model->end_date('0000-00-00');
            $model->discounttype(0);
            $model->wicable(0);
            $model->scaleprice(0);
            $model->inUse(1);
        }
        $stores = $this->formNoEx('store_id', array());
        for ($i=0; $i<count($stores); $i++) {
            $model->store_id($stores[$i]);

            $taxes = $this->formNoEx('tax', array());
            if (isset($taxes[$i])) {
                $model->tax($taxes[$i]);
            }
            $fs = $this->formNoEx('FS', array());
            $model->foodstamp(in_array($stores[$i], $fs) ? 1 : 0);
            $scale = $this->formNoEx('Scale', array());
            $model->scale(in_array($stores[$i], $scale) ? 1 : 0);
            $qtyFrc = $this->formNoEx('QtyFrc', array());
            $model->qttyEnforced(in_array($stores[$i], $qtyFrc) ? 1 : 0);
            $wic = FormLib::get('prod-wicable', array());
            $model->wicable(in_array($stores[$i], $wic) ? 1 : 0);
            $discount_setting = $this->formNoEx('discount', array());
            if (isset($discount_setting[$i])) {
                switch ($discount_setting[$i]) {
                    case 0:
                        $model->discount(0);
                        $model->line_item_discountable(0);
                        break;
                    case 1:
                        $model->discount(1);
                        $model->line_item_discountable(1);
                        break;
                    case 2:
                        $model->discount(1);
                        $model->line_item_discountable(0);
                        break;
                    case 3:
                        $model->discount(0);
                        $model->line_item_discountable(1);
                        break;
                }
            }
            $price = $this->formNoEx('price', array());
            if (isset($price[$i])) {
                $model->normal_price($price[$i]);
            }
            $cost = $this->formNoEx('cost', array());
            if (isset($cost[$i])) {
                $model->cost($cost[$i]);
            }
            $desc = $this->formNoEx('descript', array());
            if (isset($desc[$i])) {
                $model->description(str_replace("'", '', $desc[$i]));
            }
            $brand = $this->formNoEx('manufacturer', array());
            if (isset($brand[$i])) {
                $model->brand(str_replace("'", '', $brand[$i]));
            }
            $model->pricemethod(0);
            $model->groupprice(0.00);
            $model->quantity(0);
            $dept = $this->formNoEx('department', array());
            if (isset($dept[$i])) {
                $model->department($dept[$i]);
            }
            $size = $this->formNoEx('size', array());
            if (isset($size[$i])) {
                $model->size($size[$i]);
            }
            $model->modified(date('Y-m-d H:i:s'));
            $unit = FormLib::get('unitm');
            $unit = $this->formNoEx('unitm', array());
            if (isset($unit[$i])) {
                $model->unitofmeasure($unit[$i]);
            }
            $subdept = $this->formNoEx('subdept', array());
            if (isset($subdept[$i])) {
                $model->subdept($subdept[$i]);
            }

            // lookup vendorID by name
            $vendorID = 0;
            $v_input = $this->formNoEx('distributor', array());
            if (isset($v_input[$i])) {
                $vendorID = $this->getVendorID($v_input[$i]);
            }
            $model->default_vendor_id($vendorID);
            $inUse = FormLib::get('prod-in-use', array());
            $model->inUse(in_array($stores[$i], $inUse) ? 1 : 0);
            $idEnf = FormLib::get('id-enforced', array());
            if (isset($idEnf[$i])) {
                $model->idEnforced($idEnf[$i]);
            }
            $local = FormLib::get('prod-local');
            if (isset($local[$i])) {
                $model->local($local[$i]);
            }
            $deposit = FormLib::get('deposit-upc');
            if (isset($deposit[$i])) {
                if ($deposit[$i] == '') {
                    $deposit[$i] = 0;
                }
                $model->deposit($deposit[$i]);
            }
            $model->formatted_name($this->formatName($i));

            $model->save();
        }

        /**
          If a vendor is selected, intialize
          a vendorItems record
        */
        if ($vendorID != 0) {
            $this->saveVendorItem($model, $vendorID);
        }

        if ($dbc->tableExists('prodExtra')) {
            $this->saveProdExtra($model);
        }

        if (!isset($FANNIE_PRODUCT_MODULES['ProdUserModule']) && $dbc->tableExists('productUser')) {
            $this->saveProdUser($upc);
        }
    }

    private function getVendorID($name)
    {
        $dbc = $this->db();
        $vendor = new VendorsModel($dbc);
        $vendor->vendorName($name);
        foreach ($vendor->find('vendorID') as $obj) {
            return $obj->vendorID();
        }

        return 0;
    }

    private function formatName($index)
    {
        /* products.formatted_name is intended to be maintained automatically.
         * Get all enabled plugins and standard modules of the base.
         * Run plugins first, then standard modules.
         */
        $formatters = FannieAPI::ListModules('ProductNameFormatter');
        $fmt_name = "";
        $fn_params = array('index' => $index);
        foreach ($formatters as $formatter_name) {
            $formatter = new $formatter_name();
            $fmt_name = $formatter->compose($fn_params);
            if (isset($formatter->this_mod_only) &&
                $formatter->this_mod_only) {
                break;
            }
        }

        return $fmt_name;
    }

    private function saveProdUser($upc)
    {
        try {
            $dbc = $this->db();
            $model = new ProductUserModel($dbc);
            $model->upc($upc);
            $model->description($this->form->puser_description);
            return $model->save();
        } catch (Exception $ex) {
            return false;
        }
    }

    private function saveProdExtra($product)
    {
        $dbc = $this->db();
        $extra = new ProdExtraModel($dbc);
        $extra->upc($product->upc());
        if (!$extra->load()) {
            $extra->variable_pricing(0);
            $extra->margin(0);
            $extra->case_quantity('');
            $extra->case_cost(0.00);
            $extra->case_info('');
        }
        $extra->manufacturer($product->brand());
        $extra->cost($product->cost());
        try {
            $extra->distributor($this->form->distributor[0]);
        } catch (Exception $ex) {
            $extra->distributor('');
        }

        return $extra->save();
    }

    private function saveVendorItem($product, $vendorID)
    {
        $dbc = $this->db();
        $upc = $product->upc();
        /**
          If a vendor is selected, intialize
          a vendorItems record
        */
        $vitem = new VendorItemsModel($dbc);
        $vitem->vendorID($vendorID);
        $vitem->upc($upc);
        try {
            $sku = $this->form->vendorSKU;
            $caseSize = $this->form->caseSize;
            if (!empty($sku)) {
                /**
                  If a SKU is provided, update any
                  old record that used the UPC as a
                  placeholder SKU.
                */
                $existsP = $dbc->prepare('
                    SELECT sku
                    FROM vendorItems
                    WHERE sku=?
                        AND upc=?
                        AND vendorID=?');
                $existsR = $dbc->execute($existsP, array($sku, $upc, $vendorID));
                if ($dbc->numRows($existsR) > 0 && $sku != $upc) {
                    $delP = $dbc->prepare('
                        DELETE FROM vendorItems
                        WHERE sku =?
                            AND upc=?
                            AND vendorID=?');
                    $dbc->execute($delP, array($upc, $upc, $vendorID));
                } else {
                    $fixSkuP = $dbc->prepare('
                        UPDATE vendorItems
                        SET sku=?
                        WHERE sku=?
                            AND vendorID=?');
                    $dbc->execute($fixSkuP, array($sku, $upc, $vendorID));
                }
            } else {
                $sku = $upc;
            }
        } catch (Exception $ex) {
            $sku = $upc;
            $caseSize = 1;
        }
        $vitem->sku($sku);
        $vitem->size($product->size());
        $vitem->description($product->description());
        $vitem->brand($product->brand());
        $vitem->units($caseSize);
        $vitem->cost($product->cost());
        return $vitem->save();
    }

    function AjaxCallback()
    {
        $db = $this->db();
        $json = array();
        if (FormLib::get('action') == 'addVendor') {
            $name = FormLib::get('newVendorName');
            if (empty($name)) {
                $json['error'] = 'Name is required';
            } else {
                $vendor = new VendorsModel($db);
                $vendor->vendorName($name);
                if (count($vendor->find()) > 0) {
                    $json['error'] = 'Vendor "' . $name . '" already exists';
                } else {
                    $max = $db->query('SELECT MAX(vendorID) AS max
                                       FROM vendors');
                    $newID = 1;
                    if ($max && $maxW = $db->fetch_row($max)) {
                        $newID = ((int)$maxW['max']) + 1;
                    }
                    $vendor->vendorAbbreviation(substr($name, 0, 10));
                    $vendor->vendorID($newID);
                    $vendor->save();
                    $json['vendorID'] = $newID;
                    $json['vendorName'] = $name;
                }
            }
        } elseif (FormLib::get('dept_defaults') !== '') {
            $json = array('tax'=>0,'fs'=>false,'nodisc'=>false,'line'=>false);
            $dept = FormLib::get_form_value('dept_defaults','');
            $dModel = new DepartmentsModel($db);
            $dModel->dept_no($dept);
            if ($dModel->load()) {
                $json['tax'] = $dModel->dept_tax();
                $json['fs'] = $dModel->dept_fs() ? true : false;
                $json['nodisc'] = $dModel->dept_discount() ? false : true;
                $json['line'] = $dModel->line_item_discount() ? true : false;
            }
        } elseif (FormLib::get('vendorChanged') !== '') {
            $v = new VendorsModel($db);
            $v->vendorName(FormLib::get('vendorChanged'));
            $matches = $v->find();
            $json = array('error'=>false);
            if (count($matches) == 1) {
                $json['localID'] = $matches[0]->localOriginID();
                $json['vendorID'] = $matches[0]->vendorID();
            } else {
                $json['error'] = true;
            }
        }

        echo json_encode($json);
    }

    function summaryRows($upc)
    {
        $dbc = $this->db();

        $model = new ProductsModel($dbc);
        $model->upc($upc);
        if ($model->load()) {
            $row1 = '<th>UPC</th>
                <td><a href="ItemEditorPage.php?searchupc=' . $upc . '">' . $upc . '</td>
                <td>
                    <a class="iframe fancyboxLink" href="addShelfTag.php?upc='.$upc.'" title="Create Shelf Tag">Shelf Tag</a>
                </td>';
            $row2 = '<th>Description</th><td>' . $model->description() . '</td>
                     <th>Price</th><td>$' . $model->normal_price() . '</td>';

            return array($row1, $row2);
        } else {
            return array('<td colspan="4">Error saving. <a href="ItemEditorPage.php?searchupc=' . $upc . '">Try Again</a>?</td>');
        }
    }
}

/**
  This form does some fancy tricks via AJAX calls. This block
  ensures the AJAX functionality only runs when the script
  is accessed via the browser and not when it's included in
  another PHP script.
*/
if (basename($_SERVER['SCRIPT_NAME']) == basename(__FILE__)){
    $obj = new BaseItemModule();
    $obj->AjaxCallback();   
}

