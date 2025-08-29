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

        // Handle login submission
        if ($model->load(Yii::$app->request->post()) && $model->login()) {
            // Redirect to last visited page or dashboard
            return $this->redirect(Yii::$app->user->getReturnUrl(['dashboard/index']));
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
