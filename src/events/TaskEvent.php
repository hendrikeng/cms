<?php
/**
 * @link      https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license   https://craftcms.com/license
 */

namespace craft\events;

use craft\base\TaskInterface;

/**
 * TaskEvent class.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  3.0
 */
class TaskEvent extends \yii\base\Event
{
    // Properties
    // =========================================================================

    /**
     * @var TaskInterface The task associated with this event.
     */
    public $task;

    /**
     * @var boolean Whether the task is brand new
     */
    public $isNew = false;
}