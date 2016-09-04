<?php

namespace DotPlant\EntityStructure\actions;

use DevGroup\AdminUtils\actions\BaseAdminAction;
use DevGroup\DataStructure\behaviors\HasProperties;
use DevGroup\DataStructure\traits\PropertiesTrait;
use DevGroup\Multilingual\behaviors\MultilingualActiveRecord;
use DevGroup\TagDependencyHelper\TagDependencyTrait;
use DotPlant\EntityStructure\models\BaseStructure;
use Yii;
use yii\base\InvalidConfigException;
use yii\helpers\StringHelper;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;

/**
 * Class BaseEntityEditAction
 *
 * @package DotPlant\EntityStructure\actions
 */
class BaseEntityEditAction extends BaseAdminAction
{
    /** @var  BaseStructure | HasProperties | TagDependencyTrait */
    public $entityClass;

    /**
     * View file to render
     *
     * @var string
     */
    public $viewFile = '@DotPlant/EntityStructure/views/default/entity-edit';

    /**
     * Permission name to be checked for providing access to action
     *
     * @var string
     */
    public $permission = '';

    /**
     * @inheritdoc
     */
    public function init()
    {
        if (false === is_string($this->permission)) {
            throw new InvalidConfigException(
                Yii::t('dotplant.entity.structure', "Parameter 'permission' must be a string!")
            );
        }
        if (true === empty($this->entityClass)) {
            throw new InvalidConfigException(
                Yii::t('dotplant.entity.structure', "The 'entityClass' param must be set!")
            );
        }
        $entityClass = $this->entityClass;
        if (false === is_subclass_of($entityClass, BaseStructure::class)) {
            throw new InvalidConfigException(Yii::t(
                'dotplant.entity.structure',
                "The 'entityClass' must extend 'DotPlant\\EntityStructure\\models\\BaseStructure'!"
            ));
        }
        parent::init();
    }

    /**
     * @inheritdoc
     */
    public function run($id = null, $parent_id = null)
    {
        $entityClass = $this->entityClass;
        $entityName = StringHelper::basename($entityClass);
        /**
         * @var BaseStructure|HasProperties|PropertiesTrait|TagDependencyTrait|MultilingualActiveRecord $structureModel
         */
        $structureModel = $entityClass::loadModel(
            $id,
            true,
            true,
            86400,
            new NotFoundHttpException(Yii::t('dotplant.entity.structure', '{model} not found!',
                ['model' => Yii::t($entityClass::TRANSLATION_CATEGORY, $entityName)]
            ))
        );
        $refresh = !$structureModel->isNewRecord;
        if (false === $structureModel->isNewRecord) {
            $structureModel->translations;
        } else {
            $structureModel->loadDefaultValues();
            if (null !== $parent_id) {
                $structureModel->parent_id = $parent_id;
            }
        }
        $structureModel->autoSaveProperties = true;
        $post = Yii::$app->request->post();
        $structureModel->entity_id = $structureModel->getEntityId();
        $canSave = true;
        if (false === empty($this->permission) && false === Yii::$app->user->can($this->permission)) {
            $canSave = false;
        }
        if (false === empty($post)) {
            if (false === $canSave) {
                throw new ForbiddenHttpException(Yii::t('yii', 'You are not allowed to perform this action.'));
            }
            if (true === $structureModel->load($post)) {
                $translationClass = Yii::createObject($structureModel->getTranslationModelClassName());
                $translations = Yii::$app->request->post($translationClass->formName(), []);
                $translationClass = null;
                unset($translationClass);

                foreach ($translations as $language => $data) {
                    $data['parentContextId'] = (int)$structureModel->context_id;
                    $data['parentParentId'] = (int)$structureModel->parent_id;
                    $structureModel->translate($language)->oldSlug = $structureModel->translate($language)->slug;
                    foreach ($data as $attribute => $translation) {
                        $structureModel->translate($language)->$attribute = $translation;
                    }
                }
                if (true === $structureModel->validate()) {
                    if (true === $structureModel->save(false)) {
                        Yii::$app->session->setFlash('success',
                            Yii::t('dotplant.entity.structure', '{model} successfully saved!',
                                ['model' => Yii::t($entityClass::TRANSLATION_CATEGORY, $entityName)]
                            )
                        );
                        if (true === $refresh) {
                            return $this->controller->refresh();
                        } else {
                            //! @todo Move route to param
                            return $this->controller->redirect(['pages-manage/edit', 'id' => $structureModel->id]);
                        }
                    } else {
                        Yii::$app->session->setFlash('error',
                            Yii::t('dotplant.entity.structure', 'An error occurred while saving {model}!',
                                ['model' => Yii::t($entityClass::TRANSLATION_CATEGORY, $entityName)]
                            )
                        );
                    }
                } else {
                    Yii::$app->session->setFlash('warning', Yii::t(
                        'dotplant.entity.structure',
                        'Please verify that all fields are filled correctly!'
                    ));
                }
            }
        }
        return $this->controller->render(
            $this->viewFile,
            [
                'model' => $structureModel,
                'canSave' => $canSave
            ]
        );
    }
}
