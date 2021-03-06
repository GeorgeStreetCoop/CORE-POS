<?php
/*******************************************************************************

    Copyright 2014 Whole Foods Co-op, Duluth, MN

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

namespace COREPOS\Fannie\API\item\signage {

class RailSigns8x8P extends \COREPOS\Fannie\API\item\FannieSignage 
{
    protected $BIG_FONT = 10;
    protected $MED_FONT = 7;
    protected $SMALL_FONT = 6;

    protected $font = 'Arial';
    protected $alt_font = 'Arial';

    protected $width = 24; // tag width in mm
    protected $height = 31; // tag height in mm
    protected $left = 5.0; // left margin
    protected $top = 15; // top margin

    public function drawPDF()
    {
        $pdf = new \FPDF('P', 'mm', 'Letter');
        if (\COREPOS\Fannie\API\FanniePlugin::isEnabled('CoopDealsSigns')) {
            $this->font = 'Gill';
            $this->alt_font = 'GillBook';
            define('FPDF_FONTPATH', dirname(__FILE__) . '/../../../modules/plugins2.0/CoopDealsSigns/noauto/fonts/');
            $pdf->AddFont('Gill', '', 'GillSansMTPro-Medium.php');
            $pdf->AddFont('Gill', 'B', 'GillSansMTPro-Heavy.php');
        }

        $bar_width = 22;
        $pdf->SetTopMargin($this->top);  //Set top margin of the page
        $pdf->SetLeftMargin($this->left);  //Set left margin of the page
        $pdf->SetRightMargin($this->left);  //Set the right margin of the page
        $pdf->SetAutoPageBreak(False); // manage page breaks yourself

        $data = $this->loadItems();
        $num = 0; // count tags 
        $x = $this->left;
        $y = $this->top;
        $sign = 0;
        foreach ($data as $item) {

            // extract & format data
            $price = $item['normal_price'];
            $desc = $item['description'];
            $brand = strtoupper($item['brand']);

            $price = $item['normal_price'];
            if ($item['scale']) {
                if (substr($price, 0, 1) != '$') {
                    $price = sprintf('$%.2f', $price);
                }
                $price .= ' /lb.';
            } else {
                $price = sprintf('$%.2f', $price);
            }

            if ($num % 64 == 0) {
                $pdf->AddPage();
                $x = $this->left;
                $y = $this->top;
                $sign = 0;
            } else if ($num % 8 == 0) {
                $x = $this->left;
                $y += $this->height;
            }

            $row = floor($sign / 8);
            $column = $sign % 8;

            $pdf->SetFillColor(86, 90, 92);
            //$pdf->SetFillColor(12, 122, 63);
            $pdf->Rect($this->left + ($this->width*$column), $this->top + ($row*$this->height), $bar_width, 5, 'F');
            //$pdf->SetFillColor(140, 208, 36);
            $pdf->Rect($this->left + ($this->width*$column), $this->top + ($row*$this->height) + 25, $bar_width, 2, 'F');

            $pdf->SetXY($this->left + ($this->width*$column), $this->top + ($row*$this->height)+6);
            $y = $pdf->GetY();
            $pdf->SetFont($this->font, '', $this->MED_FONT);
            $pdf->MultiCell($bar_width, 5, $item['description'], 0, 'C');
            if ($pdf->GetY() - $y <= 5) {
                $pdf->Ln(5);
            }

            $pdf->SetX($this->left + ($this->width*$column));
            $pdf->SetFont($this->font, '', $this->BIG_FONT);
            $pdf->Cell($bar_width, 8, $price, 0, 1, 'C');

            // move right by tag width
            $x += $this->width;

            $num++;
            $sign++;
        }

        $pdf->Output('Tags8x8P.pdf', 'I');
    }
}

}

