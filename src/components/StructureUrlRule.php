<?php

namespace DotPlant\EntityStructure\components;

use DotPlant\EntityStructure\models\BaseStructure;
use yii;
use yii\base\Object;
use yii\web\Request;
use yii\web\UrlManager;
use yii\web\UrlRuleInterface;

class StructureUrlRule extends Object implements UrlRuleInterface
{
    const ROUTE = 'universal/show';
    const MAIN_PAGE_URL = '~mainpage~';

    /**
     * Parses the given request and returns the corresponding route and parameters.
     *
     * @param UrlManager $manager the URL manager
     * @param Request $request the request component
     *
     * @return array|boolean the parsing result. The route and the parameters are returned as an array.
     * If false, it means this rule cannot be used to parse this path info.
     */
    public function parseRequest($manager, $request)
    {
        $pathInfo = $request->getPathInfo();
        if ($pathInfo === '') {
            $pathInfo = self::MAIN_PAGE_URL;
        }
        $modelId = BaseStructure::find()
            ->where(['url' => $pathInfo])
            ->scalar();
        return $modelId !== false
            ?
                [
                    self::ROUTE,
                    [
                        'entities' => [
                            'DotPlant\EntityStructure\models\BaseStructure' => [
                                $modelId
                            ]
                        ]
                    ]
                ]
            : false;
        /*
         * Go through structure
         *
         * Find needed tree leaf
         * Find entity of it
         * Get entity route(not added in entity yet i believe)
         * Change route to needed
         */
        $cacheKey = "StructureUrlCache:$pathInfo";
        $cached = Yii::$app->cache->get($cacheKey);
        if ($cached === false) {
            $parts = explode('/', preg_replace('#/+#', '/', $pathInfo));
            /** @var BaseStructure $structure */
            $structure = null;
            foreach ($parts as $slug) {
                $structure = BaseStructure::find()
                    ->where([BaseStructure::getTranslationTableName() . '.slug' => $slug])
                    ->andWhere(['is_deleted' => false])
                    ->andWhere([BaseStructure::getTranslationTableName() . '.is_active' => true])
                    ->andWhere([
                        'parent_id' => $structure === null ? null : $structure->id
                    ])
                    ->select([
                        'id', 'entity_id'
                    ])
                    ->one();

                if ($structure === null) {
                    // @todo here we must handle the case when structure model can handle other parameters itself
                    return false;
                }
            }
//TODO get to the bottom and implement
//            $conf = $structure->getEntityConfiguration();
//            if ($conf === null) {
            return false;
//            }

            return [
                $conf['route'],
                [
                    'entities' => [
                        $conf['class_name'] => [
                            $structure->id
                        ]
                    ]
                ]
            ];

        }

        return false;
    }

    /**
     * Creates a URL according to the given route and parameters.
     *
     * @param UrlManager $manager the URL manager
     * @param string $route the route. It should not have slashes at the beginning or the end.
     * @param array $params the parameters
     *
     * @return string|boolean the created URL, or false if this rule cannot be used for creating this URL.
     */
    public function createUrl($manager, $route, $params)
    {
        // @todo: implement all available functional
        if (
            $route !== self::ROUTE
            || !isset($params['entities']['DotPlant\EntityStructure\models\BaseStructure'])
            || count($params['entities']['DotPlant\EntityStructure\models\BaseStructure']) !== 1
        ) {
            return false;
        }
        $languageId = isset($params['languageId']) ? $params['languageId'] : Yii::$app->multilingual->language_id;
        $url = (new yii\db\Query()) // it's released via Query to prevent auto-attaching of language id
            ->select(['url'])
            ->from(BaseStructure::tableName())
            ->where(['id' => $params['entities']['DotPlant\EntityStructure\models\BaseStructure'], 'language_id' => $languageId])
            ->innerJoin(BaseStructure::getTranslationTableName(), 'id = model_id')
            ->scalar();
        return $url !== self::MAIN_PAGE_URL ? $url : '';
    }
}
