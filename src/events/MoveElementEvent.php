<?php
/**
 * @link      https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license   https://craftcms.com/license
 */

namespace craft\events;

use craft\base\ElementInterface;

/**
 * Move element event class.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  3.0
 */
class MoveElementEvent extends \yii\base\Event
{
    // Properties
    // =========================================================================

    /**
     * @var int The ID of the structure the element is being moved within.
     */
    public $structureId;

    /**
     * @var ElementInterface The element being moved.
     */
    public $element;
}