<?php

namespace Concrete\Package\CommunityStoreEway;

use Package;
use Route;
use Whoops\Exception\ErrorException;
use \Concrete\Package\CommunityStore\Src\CommunityStore\Payment\Method as PaymentMethod;

class Controller extends Package
{
    protected $pkgHandle = 'community_store_eway';
    protected $appVersionRequired = '5.7.5';
    protected $pkgVersion = '0.9';

    public function getPackageDescription()
    {
        return t("Eway Payment Method for Community Store");
    }

    public function getPackageName()
    {
        return t("Eway Payment Method");
    }

    public function install()
    {
        $installed = Package::getInstalledHandles();
        if(!(is_array($installed) && in_array('community_store',$installed)) ) {
            throw new ErrorException(t('This package requires that Community Store be installed'));
        } else {
            $pkg = parent::install();
            $pm = new PaymentMethod();
            $pm->add('community_store_eway','Eway',$pkg);
        }

    }
    public function uninstall()
    {
        $pm = PaymentMethod::getByHandle('community_store_eway');
        if ($pm) {
            $pm->delete();
        }
        $pkg = parent::uninstall();
    }

    public function on_start() {
        Route::register('/checkout/ewayreturn','\Concrete\Package\CommunityStoreEway\Src\CommunityStore\Payment\Methods\CommunityStoreEway\CommunityStoreEwayPaymentMethod::EwayReturn');
    }
}
?>