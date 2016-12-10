<?php
/**
 * @link      https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license   https://craftcms.com/license
 */

namespace craft\services;

use Craft;
use craft\db\Query;
use craft\elements\GlobalSet;
use craft\errors\GlobalSetNotFoundException;
use craft\events\GlobalSetEvent;
use craft\records\GlobalSet as GlobalSetRecord;
use yii\base\Component;

/**
 * Class Globals service.
 *
 * An instance of the Globals service is globally accessible in Craft via [[Application::globals `Craft::$app->getGlobals()`]].
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  3.0
 */
class Globals extends Component
{
    // Constants
    // =========================================================================

    /**
     * @event GlobalSetEvent The event that is triggered before a global set is saved.
     *
     * You may set [[GlobalSetEvent::isValid]] to `false` to prevent the global set from getting saved.
     */
    const EVENT_BEFORE_SAVE_GLOBAL_SET = 'beforeSaveGlobalSet';

    /**
     * @event GlobalSetEvent The event that is triggered after a global set is saved.
     */
    const EVENT_AFTER_SAVE_GLOBAL_SET = 'afterSaveGlobalSet';

    // Properties
    // =========================================================================

    /**
     * @var
     */
    private $_allGlobalSetIds;

    /**
     * @var
     */
    private $_editableGlobalSetIds;

    /**
     * @var
     */
    private $_allGlobalSets;

    /**
     * @var
     */
    private $_globalSetsById;

    // Public Methods
    // =========================================================================

    /**
     * Returns all of the global set IDs.
     *
     * @return array
     */
    public function getAllSetIds()
    {
        if (!isset($this->_allGlobalSetIds)) {
            $this->_allGlobalSetIds = (new Query())
                ->select(['id'])
                ->from(['{{%globalsets}}'])
                ->column();
        }

        return $this->_allGlobalSetIds;
    }

    /**
     * Returns all of the global set IDs that are editable by the current user.
     *
     * @return array
     */
    public function getEditableSetIds()
    {
        if (!isset($this->_editableGlobalSetIds)) {
            $this->_editableGlobalSetIds = [];
            $allGlobalSetIds = $this->getAllSetIds();

            foreach ($allGlobalSetIds as $globalSetId) {
                if (Craft::$app->getUser()->checkPermission('editGlobalSet:'.$globalSetId)) {
                    $this->_editableGlobalSetIds[] = $globalSetId;
                }
            }
        }

        return $this->_editableGlobalSetIds;
    }

    /**
     * Returns all global sets.
     *
     * @param string|null $indexBy
     *
     * @return array
     */
    public function getAllSets($indexBy = null)
    {
        if (!isset($this->_allGlobalSets)) {
            $this->_allGlobalSets = GlobalSet::findAll();

            // Index them by ID
            foreach ($this->_allGlobalSets as $globalSet) {
                $this->_globalSetsById[$globalSet->id] = $globalSet;
            }
        }

        if (!$indexBy) {
            return $this->_allGlobalSets;
        }

        $globalSets = [];

        foreach ($this->_allGlobalSets as $globalSet) {
            $globalSets[$globalSet->$indexBy] = $globalSet;
        }

        return $globalSets;
    }

    /**
     * Returns all global sets that are editable by the current user.
     *
     * @param string|null $indexBy
     *
     * @return array
     */
    public function getEditableSets($indexBy = null)
    {
        $globalSets = $this->getAllSets();
        $editableGlobalSetIds = $this->getEditableSetIds();
        $editableGlobalSets = [];

        foreach ($globalSets as $globalSet) {
            if (in_array($globalSet->id, $editableGlobalSetIds)) {
                if ($indexBy) {
                    $editableGlobalSets[$globalSet->$indexBy] = $globalSet;
                } else {
                    $editableGlobalSets[] = $globalSet;
                }
            }
        }

        return $editableGlobalSets;
    }

    /**
     * Returns the total number of global sets.
     *
     * @return integer
     */
    public function getTotalSets()
    {
        return count($this->getAllSetIds());
    }

    /**
     * Returns the total number of global sets that are editable by the current user.
     *
     * @return integer
     */
    public function getTotalEditableSets()
    {
        return count($this->getEditableSetIds());
    }

    /**
     * Returns a global set by its ID.
     *
     * @param integer      $globalSetId
     * @param integer|null $siteId
     *
     * @return GlobalSet|null
     */
    public function getSetById($globalSetId, $siteId = null)
    {
        if (!$siteId) {
            $siteId = Craft::$app->getSites()->currentSite->id;
        }

        if ($siteId == Craft::$app->getSites()->currentSite->id) {
            if (!isset($this->_allGlobalSets)) {
                $this->getAllSets();
            }

            if (isset($this->_globalSetsById[$globalSetId])) {
                return $this->_globalSetsById[$globalSetId];
            }
        } else {
            return Craft::$app->getElements()->getElementById($globalSetId, GlobalSet::class, $siteId);
        }

        return null;
    }

    /**
     * Returns a global set by its handle.
     *
     * @param integer      $globalSetHandle
     * @param integer|null $siteId
     *
     * @return GlobalSet|null
     */
    public function getSetByHandle($globalSetHandle, $siteId = null)
    {
        $currentSiteId = Craft::$app->getSites()->currentSite->id;

        if (!$siteId) {
            $siteId = $currentSiteId;
        }

        if ($siteId == $currentSiteId) {
            $globalSets = $this->getAllSets();

            foreach ($globalSets as $globalSet) {
                if ($globalSet->handle == $globalSetHandle) {
                    return $globalSet;
                }
            }
        } else {
            return GlobalSet::find()
                ->siteId($siteId)
                ->handle($globalSetHandle)
                ->one();
        }

        return null;
    }

    /**
     * Saves a global set.
     *
     * @param GlobalSet $globalSet
     *
     * @return boolean
     * @throws GlobalSetNotFoundException if $globalSet->id is invalid
     * @throws \Exception if reasons
     */
    public function saveSet(GlobalSet $globalSet)
    {
        $isNewSet = !$globalSet->id;

        if (!$isNewSet) {
            $globalSetRecord = GlobalSetRecord::findOne($globalSet->id);

            if (!$globalSetRecord) {
                throw new GlobalSetNotFoundException("No global set exists with the ID '{$globalSet->id}'");
            }

            /** @var GlobalSet $oldSet */
            $oldSet = new GlobalSet($globalSetRecord->toArray([
                'name',
                'handle',
                'fieldLayoutId',
                'id',
                'uid',
                'dateCreated',
                'dateUpdated',
            ]));
        } else {
            $globalSetRecord = new GlobalSetRecord();
        }

        $globalSetRecord->name = $globalSet->name;
        $globalSetRecord->handle = $globalSet->handle;

        $globalSetRecord->validate();
        $globalSet->addErrors($globalSetRecord->getErrors());

        $success = true;

        if (!$globalSet->hasErrors()) {

            $transaction = Craft::$app->getDb()->beginTransaction();

            try {
                // Fire a 'beforeSaveGlobalSet' event
                $event = new GlobalSetEvent([
                    'globalSet' => $globalSet,
                    'isNew' => $isNewSet
                ]);

                $this->trigger(self::EVENT_BEFORE_SAVE_GLOBAL_SET, $event);

                // Is the event giving us the go-ahead?
                if ($event->isValid) {
                    $globalSet->validateCustomFields = false;
                    if (Craft::$app->getElements()->saveElement($globalSet)) {
                        // Now that we have an element ID, save it on the other stuff
                        if ($isNewSet) {
                            $globalSetRecord->id = $globalSet->id;
                        }

                        // Is there a new field layout?
                        $fieldLayout = $globalSet->getFieldLayout();

                        if (!$fieldLayout->id) {
                            // Delete the old one
                            /** @noinspection PhpUndefinedVariableInspection */
                            if (!$isNewSet && $oldSet->fieldLayoutId) {
                                Craft::$app->getFields()->deleteLayoutById($oldSet->fieldLayoutId);
                            }

                            // Save the new one
                            Craft::$app->getFields()->saveLayout($fieldLayout);

                            // Update the global set record/model with the new layout ID
                            $globalSet->fieldLayoutId = $fieldLayout->id;
                            $globalSetRecord->fieldLayoutId = $fieldLayout->id;
                        }

                        $globalSetRecord->save(false);

                        $transaction->commit();
                    }
                    $success = false;
                } else {
                    $success = false;
                }
            } catch (\Exception $e) {
                $transaction->rollBack();

                throw $e;
            }

            if ($success) {
                // Fire an 'afterSaveGlobalSet' event
                $this->trigger(self::EVENT_AFTER_SAVE_GLOBAL_SET,
                    new GlobalSetEvent([
                        'globalSet' => $globalSet,
                        'isNew' => $isNewSet
                    ])
                );
            }
        }

        return $success;
    }
}