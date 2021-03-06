<?php
namespace Mexbs\Tieredcoupon\Controller\Adminhtml\Coupon;

class Grid extends \Magento\Backend\App\Action
{
    /**
     * Point currency management main page
     *
     * @return void
     */
    public function execute()
    {
        $this->_view->loadLayout();
        $this->_setActiveMenu('Mexbs_Tieredcoupon::grid');
        $this->_view->getPage()->getConfig()->getTitle()->prepend(__('Tiered Coupons'));
        $this->_view->renderLayout();
    }
}
