<?php

/**
 * @backupGlobals disabled
 */
class TendersTest extends PHPUnit_Framework_TestCase
{
    public function testAll()
    {
        $defaults = array(
            'TenderModule',
            'CheckTender',
            'CreditCardTender',
            'DisabledTender',
            'FoodstampTender',
            'GiftCardTender',
            'GiftCertificateTender',
            'RefundAndCashbackTender',
            'StoreChargeTender',
            'StoreTransferTender'
        );

        $all = AutoLoader::ListModules('TenderModule',True);
        foreach($defaults as $d){
            $this->assertContains($d, $all);
        }

        foreach($all as $class){
            $obj = new $class('CA',1.00);
            $this->assertInstanceOf('TenderModule',$obj);

            $err = $obj->ErrorCheck();
            $this->assertThat($err,
                $this->logicalOr(
                    $this->isType('boolean',$err),
                    $this->isType('string',$err)
                )
            );

            $pre = $obj->ErrorCheck();
            $this->assertThat($pre,
                $this->logicalOr(
                    $this->isType('boolean',$pre),
                    $this->isType('string',$pre)
                )
            );

            $change = $obj->ChangeType();
            $this->assertInternalType('string',$change);
        }

    }

    function testTenderDbRecords()
    {
        lttLib::clear();
        $t = new TenderModule('CA', 1.00);
        $t->add();
        $record = lttLib::genericRecord();
        $record['trans_type'] = 'T';
        $record['trans_subtype'] = 'CA';
        $record['description'] = 'Cash';
        $record['total'] = -1.00;
        lttLib::verifyRecord(1, $record, $this);
        CoreLocal::set('currentid', 1);
        $v = new Void();
        $this->assertEquals(true, $v->check('VD'));
        $json = $v->parse('VD');
        $this->assertInternalType('array', $json);
        $record['total'] *= -1;
        $record['voided'] = 1;
        $record['trans_status'] = 'V';
        lttLib::verifyRecord(2, $record, $this);
    }

    function testTenderModule()
    {
        $t1 = new TenderModule('CA', 1.00);
        $t2 = new TenderModule('FOOBAR', 1.00);
        $this->assertEquals('CA', $t2->changeType());
        $this->assertEquals('Change', $t2->changeMsg());
        $this->assertEquals(true, $t1->allowDefault());
        $this->assertNotEquals(0, strlen($t2->disabledPrompt()));

        CoreLocal::set('amtdue', 1.99);
        $this->assertEquals(1.99, $t1->defaultTotal());
        $this->assertEquals('?quiet=1', substr($t1->defaultPrompt(), -8)); 

        CoreLocal::set('LastID', 0);
        $out = $t1->errorCheck();
        $this->assertNotEquals(0, strlen($out));
        CoreLocal::set('LastID', 1);

        CoreLocal::set('refund', 1);
        $out = $t1->errorCheck();
        $this->assertNotEquals(0, strlen($out));
        CoreLocal::set('refund', 0);

        $t3 = new TenderModule('ca', 100000);
        $out = $t3->errorCheck();
        $this->assertNotEquals(0, strlen($out));

        CoreLocal::set('ttlflag', 0);
        $out = $t1->errorCheck();
        $this->assertNotEquals(0, strlen($out));
        CoreLocal::set('ttlflag', 1);

        $out = $t2->errorCheck();
        $this->assertNotEquals(0, strlen($out));

        $out = $t1->errorCheck();
        $this->assertEquals(true, $out);

        CoreLocal::set('ttlflag', 0);
        CoreLocal::set('LastID', 0);
        CoreLocal::set('amtdue', 0);

        CoreLocal::set('msgrepeat', 0);
        $out = $t2->preReqCheck();
        $this->assertNotEquals(0, strlen($out));

        CoreLocal::set('msgrepeat', 1);
        CoreLocal::set('lastRepeat', 'confirmTenderAmount');
        $out = $t1->preReqCheck();
        $this->assertEquals(true, $out);
        $this->assertEquals(0, CoreLocal::get('msgrepeat'));
    }

    function testStoreTransferTender()
    {
        $st = new StoreTransferTender('CA', 5);
        CoreLocal::set('amtdue', 1);
        $this->assertNotEquals(0, strlen($st->errorCheck()));
        CoreLocal::set('amtdue', 8);
        $this->assertNotEquals(0, strlen($st->errorCheck()));
        CoreLocal::set('amtdue', 0);

        CoreLocal::set('transfertender', 0);
        $out = $st->preReqCheck();
        $this->assertEquals(1, CoreLocal::get('transfertender'));
        $this->assertEquals('=StoreTransferTender', substr($out, -20));
        $out = $st->preReqCheck();
        $this->assertEquals(0, CoreLocal::get('transfertender'));
        $this->assertEquals(true, $out);
    }

    function testStoreChargeTender()
    {
        $sc = new StoreChargeTender('CA', 1);
        $this->assertEquals('?autoconfirm=1', substr($sc->defaultPrompt(), -14));
        $this->assertEquals(true, $sc->preReqCheck());
    }

    function testSignedStoreChargeTender()
    {
        $sc = new SignedStoreChargeTender('CA', 1);
        CoreLocal::set('msgrepeat', 0);
        $this->assertEquals('&code=CA', substr($sc->preReqCheck(), -8));
        CoreLocal::set('msgrepeat', 1);
        CoreLocal::set('lastRepeat', 'signStoreCharge');
        $this->assertEquals(true, $sc->preReqCheck());
    }

    function testNoDefaultAmountTender()
    {
        $obj = new NoDefaultAmountTender('CA', 1);
        $this->assertEquals(false, $obj->allowDefault());
    }

    function testNoChangeTender()
    {
        $st = new NoChangeTender('CA', 5);
        CoreLocal::set('amtdue', 1);
        $this->assertNotEquals(0, strlen($st->errorCheck()));
        CoreLocal::set('amtdue', 8);
        $this->assertEquals(true, $st->errorCheck());
        CoreLocal::set('amtdue', 0);
    }

    function testManagerApproveTender()
    {
        $st = new ManagerApproveTender('CA', 5);
        CoreLocal::set('amtdue', 1);
        $this->assertNotEquals(0, strlen($st->errorCheck()));
        CoreLocal::set('amtdue', 8);
        $this->assertEquals(true, $st->errorCheck());
        CoreLocal::set('amtdue', 0);

        CoreLocal::set('approvetender', 0);
        $out = $st->preReqCheck();
        $this->assertEquals('=ManagerApproveTender', substr($out, -21));
        $this->assertEquals(1, CoreLocal::get('approvetender'));
        $out = $st->preReqCheck();
        $this->assertEquals(true, $out);
        $this->assertEquals(0, CoreLocal::get('approvetender'));
    }

    function testDisabledTender()
    {
        $obj = new DisabledTender('CA', 1);
        $this->assertNotEquals(0, strlen($obj->errorCheck()));
    }
}
