<?php
/**
 * @category  Apptrian
 * @package   Apptrian_PinterestPixel
 * @author    Apptrian
 * @copyright Copyright (c) Apptrian (http://www.apptrian.com)
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License
 */
 
namespace Apptrian\PinterestPixel\Model\Config;

use Magento\Framework\Exception\LocalizedException;

class Mapping extends \Magento\Framework\App\Config\Value
{
    /**
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function beforeSave()
    {
        $value = $this->getValue();
        $validator = \Zend_Validate::is(
            $value,
            'Regex',
            ['pattern' => '/^[\p{L}\p{N}_,;:!&#\=\+\*\$\?\|\'\.\-]*$/iu']
        );
        
        if (!$validator) {
            $message = __(
                'Product Parameters to Attributes Mapping is not valid.'
            );
            throw new LocalizedException($message);
        }
        
        return $this;
    }
}
