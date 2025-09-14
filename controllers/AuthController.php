<?php
namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\filters\AccessControl;
use app\models\LoginForm;

class AuthController extends Controller
{
public function actionLogin()
    {
        // If already logged in, go to dashboard
        if (!Yii::$app->user->isGuest) {
            return $this->redirect(['dashboard/index']);
        }

        $model = new LoginForm();

        if ($model->load(Yii::$app->request->post()) && $model->login()) {
                // After successful login → check if scenarios exist
                $db = Yii::$app->db;
                $hasScenario = $db->createCommand("
                    SELECT EXISTS(
                        SELECT 1
                        FROM oc.operational_cost_inputs
                    )
                ")->queryScalar();

                if ($hasScenario) {
                    return $this->redirect(['dashboard/index']);
                } else {
                   return $this->redirect(['operational-cost/create']);
                }
            }

        // Always clear password before re-rendering form
        $model->password = '';

        return $this->render('login', ['model' => $model]);
    }

    public function actionLogout()
    {
        Yii::$app->user->logout();
        return $this->redirect(['auth/login']);
    }

}
