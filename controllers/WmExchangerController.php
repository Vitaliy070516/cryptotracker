<?php

namespace app\controllers;

use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\Response;
use yii\filters\VerbFilter;
use app\models\WmExchangerModel;
use app\models\WmExchangerSearchModel;
use app\models\WmExchangerSettingsModel;
use app\models\WmExchangerQueryModel;
use app\models\WmExchangerQuerySearchModel;
use yii\web\NotFoundHttpException;

class WmExchangerController extends Controller
{
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'only' => ['index', 'update', 'delete', 'view'],
                'rules' => [
                    [
                        'allow' => true,
                        'actions' => ['index', 'update', 'delete', 'view'],
                        'roles' => ['@'],
                    ],
                ],
            ],
        ];
    }

    public function actions()
    {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
            'captcha' => [
                'class' => 'yii\captcha\CaptchaAction',
                'fixedVerifyCode' => YII_ENV_TEST ? 'testme' : null,
            ],
        ];
    }

    public function actionIndex()
    {
        $wmExchangerSettingsModel = new WmExchangerSettingsModel();
        if ($wmExchangerSettingsModel->load(Yii::$app->request->post()) && $wmExchangerSettingsModel->validate()) {
            $exchtype = $wmExchangerSettingsModel->exchtype;
            $maxCount = $wmExchangerSettingsModel->rows;
            $field = $wmExchangerSettingsModel->field;
            $sign = $wmExchangerSettingsModel->sign;
            $value = $wmExchangerSettingsModel->value;
            $wmExchangerSettingsModel->time_start = date('Y-m-d H:i:s', time()); // Ð²ÐºÐ»ÑŽÑ‡Ð¸Ð»Ð¸, Ñ‚.Ðº. Ð±Ð´ Ð½Ðµ Ð¿Ð¾Ð´Ð´ÐµÑ€Ð¶Ð¸Ð²Ð°ÐµÑ‚ current timestamp

            #############################################
            # Ð´Ð»Ñ cryptotracker.auto-color.com.ua Ð½ÐµÐ¾Ð±Ñ…Ð¾Ð´Ð¸Ð¼Ð¾, Ð° Ð´Ð»Ñ Ð´Ñ€ÑƒÐ³Ð¸Ñ… Ñ…Ð¾ÑÑ‚Ð¸Ð½Ð³Ð¾Ð² Ð½Ðµ Ð¿Ð¾Ð¼ÐµÑˆÐ°ÐµÑ‚
            $context = stream_context_create([
                'ssl' => [
                    "verify_peer"=>false,
                    "verify_peer_name"=>false,
                ]
            ]);
            libxml_set_streams_context($context);
            #############################################
            $xmlData = simplexml_load_file(Yii::$app->params['urlWm'] . $exchtype);
            if (!empty($xmlData->WMExchnagerQuerys)) {
                $wmExchangerSettingsModel->save();
                $wmExchangerModel = new WmExchangerModel();
                $bankRate = preg_replace('/,/', '.', $xmlData->BankRate);
                $wmExchangerModel->exchtype = $exchtype;
                $wmExchangerModel->bank_rate = $bankRate;
                $wmExchangerModel->direction = strval($xmlData->BankRate['direction']);
                $wmExchangerModel->ratetype = $xmlData->BankRate['ratetype']; 
                $wmExchangerModel->amountin = strval($xmlData->WMExchnagerQuerys['amountin']); 
                $wmExchangerModel->amountout = strval($xmlData->WMExchnagerQuerys['amountout']); 
                $wmExchangerModel->inoutrate = strval($xmlData->WMExchnagerQuerys['inoutrate']);
                $wmExchangerModel->outinrate = strval($xmlData->WMExchnagerQuerys['outinrate']);
                $wmExchangerModel->save();

                $count = 0;
                foreach ($xmlData->WMExchnagerQuerys->query as $row) {
                    if ($count == $maxCount) break;

                    if (($sign == 'all')
                        || ($sign == '<' && preg_replace('/,/', '.', $row[$field]) < $value)
                        || ($sign == '>' && preg_replace('/,/', '.', $row[$field]) > $value)
                        || ($sign == '=' && preg_replace('/,/', '.', $row[$field]) == $value)) {
                        $wmExchangerQueryModel = new WmExchangerQueryModel();
                        $wmExchangerQueryModel->id = strval($row['id']);
                        $wmExchangerQueryModel->amountin = preg_replace('/,/', '.', $row['amountin']);
                        $wmExchangerQueryModel->amountout = preg_replace('/,/', '.', $row['amountout']);
                        $wmExchangerQueryModel->inoutrate = preg_replace('/,/', '.', $row['inoutrate']);
                        $wmExchangerQueryModel->outinrate = preg_replace('/,/', '.', $row['outinrate']);
                        $wmExchangerQueryModel->procentbankrate = preg_replace('/,/', '.', $row['procentbankrate']);
                        $wmExchangerQueryModel->allamountin = preg_replace('/,/', '.', $row['allamountin']);
                        $wmExchangerQueryModel->querydate = strval($row['querydate']);
                        $wmExchangerQueryModel->exchtype = $exchtype;                       
                        $wmExchangerQueryModel->save();                     
                        $count++;
                    }
                }

                return $this->redirect(['index']);        
            }
            else {
                $message = 'ÐÐµÐ²ÐµÑ€Ð½Ñ‹Ð¹ Ð·Ð°Ð¿Ñ€Ð¾Ñ Ðº WM ÐžÐ±Ð¼ÐµÐ½Ð½Ð¸ÐºÑƒ (exchtype = ' . $exchtype . ')';
                Yii::$app->session->setFlash('message', '<span class="message">' . $message . '</span>');
            }            
        }

        $searchModel = new WmExchangerSearchModel();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
            'wmExchangerSettingsModel' => $wmExchangerSettingsModel,
        ]);
    }

    public function actionUpdate($id)
    {
        $wmExchangerSettingsModel = $this->findModel(WmExchangerSettingsModel::className(), $id);        
        if ($wmExchangerSettingsModel->load(Yii::$app->request->post()) && $wmExchangerSettingsModel->validate()) {
            $exchtype = $wmExchangerSettingsModel->exchtype;
            $maxCount = $wmExchangerSettingsModel->rows;
            $field = $wmExchangerSettingsModel->field;
            $sign = $wmExchangerSettingsModel->sign;
            $value = $wmExchangerSettingsModel->value;

            #############################################
            # Ð´Ð»Ñ cryptotracker.auto-color.com.ua Ð½ÐµÐ¾Ð±Ñ…Ð¾Ð´Ð¸Ð¼Ð¾, Ð° Ð´Ð»Ñ Ð´Ñ€ÑƒÐ³Ð¸Ñ… Ñ…Ð¾ÑÑ‚Ð¸Ð½Ð³Ð¾Ð² Ð½Ðµ Ð¿Ð¾Ð¼ÐµÑˆÐ°ÐµÑ‚
            $context = stream_context_create([
                'ssl' => [
                    "verify_peer"=>false,
                    "verify_peer_name"=>false,
                ]
            ]);
            libxml_set_streams_context($context);
            #############################################
            $xmlData = simplexml_load_file(Yii::$app->params['urlWm'] . $exchtype);
            if (!empty($xmlData->WMExchnagerQuerys)) {
                $wmExchangerSettingsModel->save();

                WmExchangerModel::deleteAll(['exchtype' => $id]);
                WmExchangerQueryModel::deleteAll(['exchtype' => $id]);

                $wmExchangerModel = new WmExchangerModel();
                $bankRate = preg_replace('/,/', '.', $xmlData->BankRate);
                $wmExchangerModel->exchtype = $exchtype;
                $wmExchangerModel->bank_rate = $bankRate;
                $wmExchangerModel->direction = strval($xmlData->BankRate['direction']);
                $wmExchangerModel->ratetype = $xmlData->BankRate['ratetype']; 
                $wmExchangerModel->amountin = strval($xmlData->WMExchnagerQuerys['amountin']); 
                $wmExchangerModel->amountout = strval($xmlData->WMExchnagerQuerys['amountout']); 
                $wmExchangerModel->inoutrate = strval($xmlData->WMExchnagerQuerys['inoutrate']);
                $wmExchangerModel->outinrate = strval($xmlData->WMExchnagerQuerys['outinrate']);
                $wmExchangerModel->save();

                $count = 0;
                foreach ($xmlData->WMExchnagerQuerys->query as $row) {
                    if ($count == $maxCount) break;

                    if (($sign == 'all')
                        || ($sign == '<' && preg_replace('/,/', '.', $row[$field]) < $value)
                        || ($sign == '>' && preg_replace('/,/', '.', $row[$field]) > $value)
                        || ($sign == '=' && preg_replace('/,/', '.', $row[$field]) == $value)) {
                        $wmExchangerQueryModel = new WmExchangerQueryModel();
                        $wmExchangerQueryModel->id = strval($row['id']);
                        $wmExchangerQueryModel->amountin = preg_replace('/,/', '.', $row['amountin']);
                        $wmExchangerQueryModel->amountout = preg_replace('/,/', '.', $row['amountout']);
                        $wmExchangerQueryModel->inoutrate = preg_replace('/,/', '.', $row['inoutrate']);
                        $wmExchangerQueryModel->outinrate = preg_replace('/,/', '.', $row['outinrate']);
                        $wmExchangerQueryModel->procentbankrate = preg_replace('/,/', '.', $row['procentbankrate']);
                        $wmExchangerQueryModel->allamountin = preg_replace('/,/', '.', $row['allamountin']);
                        $wmExchangerQueryModel->querydate = strval($row['querydate']);
                        $wmExchangerQueryModel->exchtype = $exchtype;                       
                        $wmExchangerQueryModel->save();                     
                        $count++;
                    }
                }

                return $this->redirect(['index']);        
            }
            else {
                $message = 'ÐÐµÐ²ÐµÑ€Ð½Ñ‹Ð¹ Ð·Ð°Ð¿Ñ€Ð¾Ñ Ðº WM ÐžÐ±Ð¼ÐµÐ½Ð½Ð¸ÐºÑƒ (exchtype = ' . $exchtype . ')';
                Yii::$app->session->setFlash('message', '<span class="message">' . $message . '</span>');
            }           
        }        

        return $this->render('update', [
            'wmExchangerSettingsModel' => $wmExchangerSettingsModel,
        ]);
    }

    public function actionView($id)
    {
        $wmExchangerQueryModels = WmExchangerQueryModel::findAll(['exchtype' => $id]);    

        $searchModel = new WmExchangerQuerySearchModel();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams, $id);

        return $this->render('view', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    public function actionDelete($id)
    {
        $wmExchangerSettingsModel = $this->findModel(WmExchangerSettingsModel::className(), $id); 
        $wmExchangerSettingsModel->delete();
        WmExchangerModel::deleteAll(['exchtype' => $id]);
        WmExchangerQueryModel::deleteAll(['exchtype' => $id]);
        
        return $this->redirect(['index']);
    }

    protected function findModel($className, $id)
    {
        if (($model = $className::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested model does not exist.');
        }
    }
}