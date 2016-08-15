<?php

namespace DotPlant\EntityStructure\actions;


use DevGroup\AdminUtils\actions\BaseAdminAction;
use DotPlant\EntityStructure\models\BaseStructure;
use DotPlant\EntityStructure\StructureModule;
use yii\base\InvalidConfigException;
use Yii;
use yii\web\NotFoundHttpException;

class BaseEntityDeleteAction extends BaseAdminAction
{
    /** @var  BaseStructure */
    public $entityClass;

    /** @var  array custom route to redirect to */
    public $redirectUrl;

    /**
     * @inheritdoc
     */
    public function init()
    {
        if (true === empty($this->entityClass)) {
            throw new InvalidConfigException(
                Yii::t(StructureModule::TRANSLATION_CATEGORY, "The 'entityClass' param must be set!")
            );
        }
        $entityClass = $this->entityClass;
        if (false === is_subclass_of($entityClass, BaseStructure::class)) {
            throw new InvalidConfigException(Yii::t(
                StructureModule::TRANSLATION_CATEGORY,
                "The 'entityClass' must extend 'DotPlant\\EntityStructure\\models\\BaseStructure'!"
            ));
        }
        parent::init();
    }

    /**
     * @inheritdoc
     */
    public function run($id, $returnUrl = '', $hard = null)
    {
        $entityClass = $this->entityClass;
        /** @var BaseStructure $model */
        $model = $entityClass::loadModel(
            $id,
            true,
            true,
            86400,
            new NotFoundHttpException(Yii::t(
                StructureModule::TRANSLATION_CATEGORY,
                'Record with id {id} not found!',
                ['id' => $id]
            ))
        );
        if (method_exists($model, 'hardDelete')) {
            if ((int)$hard === 1) {
                if (false !== $model->hardDelete()) {
                    Yii::$app->session->setFlash(
                        'info',
                        Yii::t(StructureModule::TRANSLATION_CATEGORY, 'Record has been successfully deleted.')
                    );
                } else {
                    Yii::$app->session->setFlash(
                        'warning',
                        Yii::t(StructureModule::TRANSLATION_CATEGORY, 'An error occurred while deleting record.')
                    );
                }
            } else {
                if (false === $model->delete() && true === $model->isDeleted()) {
                    Yii::$app->session->setFlash(
                        'info',
                        Yii::t(StructureModule::TRANSLATION_CATEGORY, 'Record has been successfully hidden.')
                    );
                } else {
                    Yii::$app->session->setFlash('warning', Yii::t(
                        StructureModule::TRANSLATION_CATEGORY,
                        'An error occurred while attempting to hide record.'
                    ));
                }
            }
        } else {
            if (false !== $model->delete()) {
                Yii::$app->session->setFlash(
                    'info',
                    Yii::t(StructureModule::TRANSLATION_CATEGORY, 'Record has been successfully deleted.')
                );
            } else {
                Yii::$app->session->setFlash(
                    'warning',
                    Yii::t(StructureModule::TRANSLATION_CATEGORY, 'An error occurred while deleting record.')
                );
            }
        }
        $returnUrl = empty($this->redirectUrl)
            ? (empty($returnUrl) ? 'index' : $returnUrl)
            : $this->redirectUrl;
        return $this->controller->redirect($returnUrl);
    }
}