<?php

use app\components\Tools;
use app\components\UrlHelper;
use app\models\db\Consultation;
use app\models\db\Motion;
use yii\helpers\Html;

/**
 * @var $this yii\web\View
 * @var Consultation $consultation
 * @var Motion $motion
 */

/** @var \app\controllers\Base $controller */
$controller = $this->context;
$layout     = $controller->layoutParams;

$this->title = 'Antrag bearbeiten: ' . $motion->getTitleWithPrefix();
$layout->addBreadcrumb('Administration', UrlHelper::createUrl('admin/index'));
$layout->addBreadcrumb('Anträge', UrlHelper::createUrl('admin/motion/index'));
$layout->addBreadcrumb('Antrag');

$layout->addJS('/js/backend.js');
$layout->addCSS('/css/backend.css');
$layout->addJS('/js/bower/moment/min/moment-with-locales.min.js');
$layout->addJS('/js/bower/eonasdan-bootstrap-datetimepicker/build/js/bootstrap-datetimepicker.min.js');
$layout->addCSS('/js/bower/eonasdan-bootstrap-datetimepicker/build/css/bootstrap-datetimepicker.min.css');

echo '<h1>' . Html::encode($motion->getTitleWithPrefix()) . '</h1>';

echo $controller->showErrors();


if ($motion->status == Motion::STATUS_SUBMITTED_UNSCREENED) {
    echo Html::beginForm('', 'post', ['class' => 'content', 'id' => 'motionScreenForm']);
    $newRev = $motion->titlePrefix;
    if ($newRev == '') {
        $newRev = $motion->consultation->getNextAvailableStatusString($motion->motionTypeId);
    }

    echo '<input type="hidden" name="titlePrefix" value="' . Html::encode($newRev) . '">';

    echo '<div style="text-align: center;"><button type="submit" class="btn btn-primary" name="screen">';
    echo Html::encode('Freischalten als ' . $newRev);
    echo '</button></div>';

    echo Html::endForm();

    echo "<br>";
}


echo Html::beginForm('', 'post', ['class' => 'content', 'id' => 'motionUpdateForm']);

echo '<div class="form-group">';
echo '<label class="col-md-4 control-label" for="parentMotion">';
echo 'Überarbeitete Fassung von';
echo ':</label><div class="col-md-8">';
echo '<select class="form-control" name="motion[parentMotionId]" size="1" id="parentMotion"><option>-</option>';
foreach ($consultation->motions as $mot) {
    if ($mot->id != $motion->id) {
        echo '<option value="' . $mot->id . '"';
        if ($motion->parentMotionId == $mot->id) {
            echo ' selected';
        }
        echo '>' . Html::encode($mot->getTitleWithPrefix()) . '</option>';
    }
}
echo '</select></div></div>';

echo '<div class="form-group">';
echo '<label class="col-md-4 control-label" for="motionStatus">';
echo 'Status';
echo ':</label><div class="col-md-4">';
$options = ['class' => 'form-control', 'id' => 'motionStatus'];
echo Html::dropDownList('motion[status]', $motion->status, Motion::getStati(), $options);
echo '</div><div class="col-md-4">';
$options = ['class' => 'form-control', 'id' => 'motionStatusString', 'placeholder' => '...'];
echo Html::textInput('motion[statusString]', $motion->statusString, $options);
echo '</div></div>';


echo '<div class="form-group">';
echo '<label class="col-md-4 control-label" for="motionTitle">';
echo 'Titel';
echo ':</label><div class="col-md-8">';
$options = ['class' => 'form-control', 'id' => 'motionTitle', 'placeholder' => 'Titel'];
echo Html::textInput('motion[title]', $motion->title, $options);
echo '</div></div>';


echo '<div class="form-group">';
echo '<label class="col-md-4 control-label" for="motionTitlePrefix">';
echo 'Antragskürzel';
echo ':</label><div class="col-md-8">';
$options = ['class' => 'form-control', 'id' => 'motionTitlePrefix', 'placeholder' => 'z.B. "A1"'];
echo Html::textInput('motion[titlePrefix]', $motion->titlePrefix, $options);
echo '<small>z.B. "A1", "A1neu", "S1" etc. Muss unbedingt gesetzt und eindeutig sein.</small>';
echo '</div></div>';


$locale = Tools::getCurrentDateLocale();

$date   = Tools::dateSql2bootstraptime($motion->dateCreation);
echo '<div class="form-group">';
echo '<label class="col-md-4 control-label" for="motionDateCreation">';
echo 'Angelegt am';
echo ':</label><div class="col-md-8"><div class="input-group date" id="motionDateCreationHolder">';
echo '<input type="text" class="form-control" name="motion[dateCreation]" id="motionDateCreation"
                value="' . Html::encode($date) . '" data-locale="' . Html::encode($locale) . '">
            <span class="input-group-addon"><span class="glyphicon glyphicon-calendar"></span>';
echo '</div></div></div>';

$date   = Tools::dateSql2bootstraptime($motion->dateResolution);
echo '<div class="form-group">';
echo '<label class="col-md-4 control-label" for="motionDateResolution">';
echo 'Beschlossen am';
echo ':</label><div class="col-md-8"><div class="input-group date" id="motionDateResolutionHolder">';
echo '<input type="text" class="form-control" name="motion[dateResolution]" id="motionDateResolution"
                value="' . Html::encode($date) . '" data-locale="' . Html::encode($locale) . '">
            <span class="input-group-addon"><span class="glyphicon glyphicon-calendar"></span>';
echo '</div></div></div>';

$layout->addOnLoadJS(
    '
    var lang = $("html").attr("lang");
    $("#motionDateCreationHolder").datetimepicker({
            locale: lang,
    });
    $("#motionDateResolutionHolder").datetimepicker({
            locale: lang,
    });
    '
);


echo '<div class="form-group">';
echo '<label class="col-md-4 control-label" for="motionNoteInternal">';
echo 'Interne Notiz';
echo ':</label><div class="col-md-8">';
$options = ['class' => 'form-control', 'id' => 'motionNoteInternal'];
echo Html::textarea('motion[noteInternal]', $motion->noteInternal, $options);
echo '</div></div>';


// @TODO UnterstützerInnen


echo '<div class="saveholder">
<button type="submit" name="save" class="btn btn-primary">Speichern</button>
</div>';

echo Html::endForm();
