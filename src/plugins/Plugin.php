<?php

namespace craftcom\plugins;

use Craft;
use craft\base\Element;
use craft\db\Query;
use craft\elements\actions\SetStatus;
use craft\elements\Asset;
use craft\elements\Category;
use craft\elements\db\ElementQueryInterface;
use craft\elements\User;
use craft\helpers\ArrayHelper;
use craftcom\behaviors\Developer;
use craftcom\composer\Package;
use craftcom\Module;
use yii\base\InvalidConfigException;

/**
 * @property User       $developer
 * @property Package    $package
 * @property string     $eagerLoadedElements
 * @property Asset|null $icon
 */
class Plugin extends Element
{
    // Constants
    // =========================================================================

    const STATUS_PENDING = 'pending';

    // Static
    // =========================================================================

    /**
     * @return string
     */
    public static function displayName(): string
    {
        return 'Plugin';
    }

    public static function hasStatuses(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_ENABLED => Craft::t('app', 'Enabled'),
            self::STATUS_PENDING => Craft::t('app', 'Pending Approval'),
            self::STATUS_DISABLED => Craft::t('app', 'Disabled')
        ];
    }

    /**
     * @return PluginQuery
     */
    public static function find(): ElementQueryInterface
    {
        return new PluginQuery(static::class);
    }

    /**
     * @param ElementQueryInterface $elementQuery
     * @param array|null            $disabledElementIds
     * @param array                 $viewState
     * @param string|null           $sourceKey
     * @param string|null           $context
     * @param bool                  $includeContainer
     * @param bool                  $showCheckboxes
     *
     * @return string
     */
    public static function indexHtml(ElementQueryInterface $elementQuery, array $disabledElementIds = null, array $viewState, string $sourceKey = null, string $context = null, bool $includeContainer, bool $showCheckboxes): string
    {
        $elementQuery->with(['icon', 'primaryCategory']);
        return parent::indexHtml($elementQuery, $disabledElementIds, $viewState, $sourceKey, $context, $includeContainer, $showCheckboxes); // TODO: Change the autogenerated stub
    }

    /**
     * @param array  $sourceElements
     * @param string $handle
     *
     * @return array|bool|false
     */
    public static function eagerLoadingMap(array $sourceElements, string $handle)
    {
        switch ($handle) {
            case 'developer':
                $query = (new Query())
                    ->select(['id as source', 'developerId as target'])
                    ->from(['craftcom_plugins'])
                    ->where(['id' => ArrayHelper::getColumn($sourceElements, 'id')]);
                return ['elementType' => User::class, 'map' => $query->all()];

            case 'icon':
                $query = (new Query())
                    ->select(['id as source', 'iconId as target'])
                    ->from(['craftcom_plugins'])
                    ->where(['id' => ArrayHelper::getColumn($sourceElements, 'id')])
                    ->andWhere(['not', ['iconId' => null]]);
                return ['elementType' => Asset::class, 'map' => $query->all()];

            case 'categories':
            case 'primaryCategory':
                $query = (new Query())
                    ->select(['p.id as source', 'pc.categoryId as target'])
                    ->from(['craftcom_plugins p'])
                    ->innerJoin(['craftcom_plugincategories pc'], '[[pc.pluginId]] = [[p.id]]')
                    ->where(['p.id' => ArrayHelper::getColumn($sourceElements, 'id')])
                    ->orderBy(['pc.sortOrder' => SORT_ASC]);
                if ($handle === 'primaryCategory') {
                    $query->andWhere(['pc.sortOrder' => 1]);
                }
                return ['elementType' => Category::class, 'map' => $query->all()];

            case 'screenshots':
                $query = (new Query())
                    ->select(['p.id as source', 'ps.assetId as target'])
                    ->from(['craftcom_plugins p'])
                    ->innerJoin(['craftcom_pluginscreenshots ps'], '[[ps.pluginId]] = [[p.id]]')
                    ->where(['p.id' => ArrayHelper::getColumn($sourceElements, 'id')])
                    ->orderBy(['ps.sortOrder' => SORT_ASC]);
                return ['elementType' => Asset::class, 'map' => $query->all()];

            default:
                return parent::eagerLoadingMap($sourceElements, $handle);
        }
    }

    protected static function defineSources(string $context = null): array
    {
        $sources = [
            [
                'key' => '*',
                'label' => 'All Plugins',
                'criteria' => ['status' => null],
            ],
            [
                'heading' => 'Categories',
            ],
        ];

        $categories = Category::find()
            ->group('pluginCategories')
            ->with('icon')
            ->all();

        foreach ($categories as $category) {
            $sources[] = [
                'key' => 'category:'.$category->id,
                'label' => $category->title,
                'criteria' => ['categoryId' => $category->id],
            ];
        }

        return $sources;
    }

    protected static function defineActions(string $source = null): array
    {
        return [
            SetStatus::class,
        ];
    }

    protected static function defineSearchableAttributes(): array
    {
        return [
            'developerName',
            'packageName',
            'repository',
            'name',
            'handle',
        ];
    }

    protected static function defineTableAttributes(): array
    {
        return [
            'name' => 'Name',
            'handle' => 'Handle',
            'packageName' => 'Package Name',
            'repository' => 'Repository',
            'price' => 'Price',
            'renewalPrice' => 'Renewal Price',
            'license' => 'License',
            'primaryCategory' => 'Primary Category',
            'documentationUrl' => 'Documentation URL',
        ];
    }

    protected static function defineDefaultTableAttributes(string $source): array
    {
        return [
            'name',
            'handle',
            'packageName',
            'repository',
            'price',
            'renewalPrice',
            'license',
            'primaryCategory',
        ];
    }

    // Properties
    // =========================================================================

    /**
     * @var int The developer’s user ID
     */
    public $developerId;

    /**
     * @var int The Composer package ID
     */
    public $packageId;

    /**
     * @var int|null The icon asset’s ID
     */
    public $iconId;

    /**
     * @var string|null Composer package name
     */
    public $packageName;

    /**
     * @var string The VCS repository URL
     */
    public $repository;

    /**
     * @var string The plugin name
     */
    public $name;

    /**
     * @var string The plugin handle
     */
    public $handle;

    /**
     * @var int|null The plugin license price
     */
    public $price;

    /**
     * @var int|null The plugin license renewal price
     */
    public $renewalPrice;

    /**
     * @var string The license type ('mit', 'craft')
     */
    public $license = 'craft';

    /**
     * @var string|null The plugin’s short description
     */
    public $shortDescription;

    /**
     * @var string|null The plugin’s long description
     */
    public $longDescription;

    /**
     * @var string|null The plugin’s documentation URL
     */
    public $documentationUrl;

    /**
     * @var string|null The plugin’s changelog path
     */
    public $changelogPath;

    /**
     * @var string|null The latest version available for the plugin
     */
    public $latestVersion;

    /**
     * @var bool Whether the plugin is pending approval.
     */
    public $pendingApproval = false;

    /**
     * @var User|null
     */
    private $_developer;

    /**
     * @var Package|null
     */
    private $_package;

    /**
     * @var Asset|null
     */
    private $_icon;

    /**
     * @var Category[]|null
     */
    private $_categories;

    /**
     * @var Asset[]|null
     */
    private $_screenshots;

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->name;
    }

    // Public Methods
    // =========================================================================

    /**
     * @param string $handle
     * @param array  $elements
     */
    public function setEagerLoadedElements(string $handle, array $elements)
    {
        switch ($handle) {
            case 'developer':
                $this->_developer = $elements[0] ?? null;
                break;
            case 'icon':
                $this->_icon = $elements[0] ?? null;
                break;
            case 'categories':
            case 'primaryCategory':
                $this->setCategories($elements);
                break;
            case 'screenshots':
                $this->setScreenshots($elements);
                break;
            default:
                parent::setEagerLoadedElements($handle, $elements);
        }
    }

    /**
     * @return User|Developer
     * @throws InvalidConfigException
     */
    public function getDeveloper(): User
    {
        if ($this->_developer !== null) {
            return $this->_developer;
        }
        if ($this->developerId === null) {
            throw new InvalidConfigException('Plugin is missing its developer ID');
        }
        if (($user = User::find()->id($this->developerId)->status(null)->one()) === false) {
            throw new InvalidConfigException('Invalid developer ID: '.$this->developerId);
        }
        return $this->_developer = $user;
    }

    /**
     * @return Package
     * @throws InvalidConfigException
     */
    public function getPackage(): Package
    {
        if ($this->_package !== null) {
            return $this->_package;
        }
        if ($this->packageId === null) {
            throw new InvalidConfigException('Plugin is missing its package ID');
        }
        return $this->_package = Module::getInstance()->getPackageManager()->getPackageById($this->packageId);
    }

    /**
     * @return string
     */
    public function getDeveloperName(): string
    {
        return $this->getDeveloper()->getDeveloperName();
    }

    /**
     * @return Asset|null
     * @throws InvalidConfigException
     */
    public function getIcon()
    {
        if ($this->_icon !== null) {
            return $this->_icon;
        }
        if ($this->iconId === null) {
            return null;
        }
        if (($asset = Asset::find()->id($this->iconId)->one()) === false) {
            throw new InvalidConfigException('Invalid asset ID: '.$this->iconId);
        }
        return $this->_icon = $asset;
    }

    /**
     * @return Category[]
     */
    public function getCategories(): array
    {
        if ($this->_categories !== null) {
            return $this->_categories;
        }
        return $this->_categories = Category::find()
            ->innerJoin(['craftcom_plugincategories pc'], [
                'and',
                '[[pc.categoryId]] = [[categories.id]]',
                ['pc.pluginId' => $this->id]
            ])
            ->orderBy(['pc.sortOrder' => SORT_ASC])
            ->all();
    }

    /**
     * @param Category[] $categories
     */
    public function setCategories(array $categories)
    {
        $this->_categories = $categories;
    }

    /**
     * @return Asset[]
     */
    public function getScreenshots(): array
    {
        if ($this->_screenshots !== null) {
            return $this->_screenshots;
        }
        return $this->_screenshots = Asset::find()
            ->innerJoin(['craftcom_pluginscreenshots ps'], [
                'and',
                '[[ps.assetId]] = [[assets.id]]',
                ['ps.pluginId' => $this->id]
            ])
            ->orderBy(['ps.sortOrder' => SORT_ASC])
            ->all();
    }

    /**
     * @param Asset[] $screenshots
     */
    public function setScreenshots(array $screenshots)
    {
        $this->_screenshots = $screenshots;
    }

    public function rules()
    {
        $rules = parent::rules();

        $rules[] = [
            [
                'developerId',
                'packageName',
                'repository',
                'name',
                'handle',
                'license',
            ],
            'required',
        ];

        $rules[] = [
            [
                'id',
                'developerId',
                'packageId',
                'iconId',
            ],
            'number',
            'integerOnly' => true,
        ];

        $rules[] = [
            [
                'repository',
                'documentationUrl',
            ],
            'url',
        ];

        $rules[] = [
            [
                'categories',
            ],
            'required',
            'on' => self::SCENARIO_LIVE,
        ];

        return $rules;
    }

    /**
     * @param bool $isNew
     */
    public function afterSave(bool $isNew)
    {
        $packageManager = Module::getInstance()->getPackageManager();
        if ($packageManager->packageExists($this->packageName)) {
            $package = $packageManager->getPackage($this->packageName);
            if ($package->type !== 'craft-plugin' || $package->repository !== $this->repository || !$package->managed) {
                $package->type = 'craft-plugin';
                $package->repository = $this->repository;
                $package->managed = true;
                $packageManager->savePackage($package);
            }
        } else {
            $package = new Package([
                'name' => $this->packageName,
                'type' => 'craft-plugin',
                'repository' => $this->repository,
                'managed' => true,
            ]);
            $packageManager->savePackage($package);
            $packageManager->updatePackage($package->name, false, true);
            Module::getInstance()->getJsonDumper()->dump(true);
        }

        $this->packageId = $package->id;

        if ($this->enabled) {
            $this->pendingApproval = false;
        }

        $pluginData = [
            'id' => $this->id,
            'developerId' => $this->developerId,
            'packageId' => $this->packageId,
            'iconId' => $this->iconId,
            'packageName' => $this->packageName,
            'repository' => $this->repository,
            'name' => $this->name,
            'handle' => $this->handle,
            'price' => $this->price ?: null,
            'renewalPrice' => $this->renewalPrice ?: null,
            'license' => $this->license,
            'shortDescription' => $this->shortDescription,
            'longDescription' => $this->longDescription,
            'documentationUrl' => $this->documentationUrl,
            'changelogPath' => $this->changelogPath ?: null,
            'pendingApproval' => $this->pendingApproval,
        ];

        $categoryData = [];
        foreach ($this->getCategories() as $i => $category) {
            $categoryData[] = [$this->id, $category->id, $i + 1];
        }

        $screenshotData = [];
        foreach ($this->getScreenshots() as $i => $screenshot) {
            $screenshotData[] = [$this->id, $screenshot->id, $i + 1];
        }

        $db = Craft::$app->getDb();

        if ($isNew) {
            $db->createCommand()
                ->insert('craftcom_plugins', $pluginData)
                ->execute();
        } else {
            $db->createCommand()
                ->update('craftcom_plugins', $pluginData, ['id' => $this->id])
                ->execute();

            // Also delete any existing category/screenshot relations
            $db->createCommand()
                ->delete('craftcom_plugincategories', ['pluginId' => $this->id])
                ->execute();
            $db->createCommand()
                ->delete('craftcom_pluginscreenshots', ['pluginId' => $this->id])
                ->execute();
        }

        $db->createCommand()
            ->batchInsert('craftcom_plugincategories', ['pluginId', 'categoryId', 'sortOrder'], $categoryData)
            ->execute();
        $db->createCommand()
            ->batchInsert('craftcom_pluginscreenshots', ['pluginId', 'assetId', 'sortOrder'], $screenshotData)
            ->execute();

        parent::afterSave($isNew);
    }

    public function getThumbUrl(int $size)
    {
        if ($this->iconId) {
            return Craft::$app->getAssets()->getThumbUrl($this->getIcon(), $size, false);
        }
        return null;
    }

    /**
     * @inheritdoc
     */
    public function getStatus()
    {
        if (!$this->enabled && $this->pendingApproval) {
            return self::STATUS_PENDING;
        }

        return parent::getStatus();
    }

    public function getCpEditUrl()
    {
        return "plugins/{$this->id}-{$this->handle}";
    }

    // Protected Methods
    // =========================================================================

    protected function tableAttributeHtml(string $attribute): string
    {
        switch ($attribute) {
            case 'handle':
                return "<code>{$this->handle}</code>";
            case 'packageName':
                return "<a href='http://packagist.org/packages/{$this->packageName}' target='_blank'>{$this->packageName}</a>";
            case 'repository':
            case 'documentationUrl':
                return $this->$attribute ? "<a href='{$this->$attribute}' target='_blank'>{$this->$attribute}</a>" : '';
            case 'price':
            case 'renewalPrice':
                return $this->$attribute ? Craft::$app->getFormatter()->asCurrency($this->$attribute, 'USD') : 'Free';
            case 'license':
                return $this->license === 'craft' ? 'Craft' : 'MIT';
            case 'primaryCategory':
                if ($category = ($this->getCategories()[0] ?? null)) {
                    return Craft::$app->getView()->renderTemplate('_elements/element', [
                        'element' => $category
                    ]);
                }
                return '';
            default:
                return parent::tableAttributeHtml($attribute);
        }
    }
}
