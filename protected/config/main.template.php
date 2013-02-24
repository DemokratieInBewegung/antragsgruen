<?php

// uncomment the following to define a path alias
// Yii::setPathOfAlias('local','path/to/local-folder');

// This is the main Web application configuration. Any writable
// CWebApplication properties can be configured here.

mb_internal_encoding("UTF-8");

define("SEED_KEY", "randomkey");

Yii::setPathOfAlias('bootstrap', dirname(__FILE__) . '/../../vendor/chris83/yii-bootstrap');

return array(
	'basePath'   => dirname(__FILE__) . DIRECTORY_SEPARATOR . '..',
	'name'       => 'Grüne Anträge',

	// preloading 'log' component
	'preload'    => array(
		'log',
		'bootstrap',
	),

	// autoloading model and component classes
	'import'     => array(
		'application.models.*',
		'application.components.*',
		'ext.giix-components.*',
	),

	'modules'    => array( // uncomment the following to enable the Gii tool
		/*
		'gii'=> array(
			'class'          => 'system.gii.GiiModule',
			'password'       => 'verysecurepassword',
			// If removed, Gii defaults to localhost only. Edit carefully to taste.
			'ipFilters'      => array('*', '::1'),
			'generatorPaths' => array(
				'ext.giix-core', // giix generators
				'bootstrap.gii',
			),
		),
		*/
	),

	// application components
	'components' => array(
		'user'           => array(
			// enable cookie-based authentication
			'allowAutoLogin' => true,
			'loginUrl'       => array('site/login'),
		),
		'urlManager'     => array(
			'urlFormat'      => 'path',
			'showScriptName' => false,
			'rules'          => array(
				'<veranstaltung_id:[\w_-]+>/admin'                                                                           => 'admin/index',
				'/admin/index'                                                                                               => 'admin/index',
				'/admin/veranstaltungen'                                                                                     => 'admin/veranstaltungen',
				'/admin/veranstaltungen/<_a:(index|create|update|delete|view|admin)>'                                        => 'admin/veranstaltungen/<_a>',
				'<veranstaltung_id:[\w_-]+>/admin/antraege'                                                                  => 'admin/antraege',
				'<veranstaltung_id:[\w_-]+>/admin/antraege/<_a:(index|create|update|delete|view|admin)>'                     => 'admin/antraege/<_a>',
				'<veranstaltung_id:[\w_-]+>/admin/aenderungsantraege'                                                        => 'admin/aenderungsantraege',
				'<veranstaltung_id:[\w_-]+>/admin/aenderungsantraege/<_a:(index|create|update|delete|view|admin)>'           => 'admin/aenderungsantraege/<_a>',
				'<veranstaltung_id:[\w_-]+>/admin/antraegeKommentare'                                                        => 'admin/antraegeKommentare',
				'<veranstaltung_id:[\w_-]+>/admin/antraegeKommentare/<_a:(index|create|update|delete|view|admin)>'           => 'admin/antraegeKommentare/<_a>',
				'<veranstaltung_id:[\w_-]+>/admin/aenderungsantraegeKommentare'                                              => 'admin/aenderungsantraegeKommentare',
				'<veranstaltung_id:[\w_-]+>/admin/aenderungsantraegeKommentare/<_a:(index|create|update|delete|view|admin)>' => 'admin/aenderungsantraegeKommentare/<_a>',
				'<veranstaltung_id:[\w_-]+>/admin/texte'                                                                     => 'admin/texte',
				'<veranstaltung_id:[\w_-]+>/admin/texte/<_a:(index|create|update|delete|view|admin)>'                        => 'admin/texte/<_a>',
				'<veranstaltung_id:[\w_-]+>/hilfe'                                                                           => 'site/hilfe',
				'<veranstaltung_id:[\w_-]+>/login'                                                                           => 'site/login',
				'<veranstaltung_id:[\w_-]+>/suche'                                                                           => 'site/suche',
				'<veranstaltung_id:[\w_-]+>/impressum'                                                                       => 'site/impressum',
				'<veranstaltung_id:[\w_-]+>/logout'                                                                          => 'site/logout',
				'<veranstaltung_id:[\w_-]+>/'                                                                                => 'site/veranstaltung',
				'<veranstaltung_id:[\w_-]+>/feedAlles'                                                                       => 'site/feedAlles',
				'<veranstaltung_id:[\w_-]+>/feedAntraege'                                                                    => 'site/feedAntraege',
				'<veranstaltung_id:[\w_-]+>/feedAenderungsantraege'                                                          => 'site/feedAenderungsantraege',
				'<veranstaltung_id:[\w_-]+>/feedKommentare'                                                                  => 'site/feedKommentare',
				'<veranstaltung_id:[\w_-]+>/antrag/neu'                                                                      => 'antrag/neu',
				'<veranstaltung_id:[\w_-]+>/antrag/<antrag_id:\d+>?kommentar_id=<kommentar_id:\d+>'                          => 'antrag/anzeige',
				'<veranstaltung_id:[\w_-]+>/antrag/<antrag_id:\d+>'                                                          => 'antrag/anzeige',
				'<veranstaltung_id:[\w_-]+>/antrag/<antrag_id:\d+>/pdf'                                                      => 'antrag/pdf',
				'<veranstaltung_id:[\w_-]+>/antrag/<antrag_id:\d+>/neuConfirm'                                               => 'antrag/neuConfirm',
				'<veranstaltung_id:[\w_-]+>/antrag/<antrag_id:\d+>/aendern'                                                  => 'antrag/aendern',
				'<veranstaltung_id:[\w_-]+>/antrag/<antrag_id:\d+>/aenderungsantrag/<aenderungsantrag_id:\d+>'               => 'aenderungsantrag/anzeige',
				'<veranstaltung_id:[\w_-]+>/antrag/<antrag_id:\d+>/aenderungsantrag/<aenderungsantrag_id:\d+>/neuConfirm'    => 'aenderungsantrag/neuConfirm',
				'<veranstaltung_id:[\w_-]+>/antrag/<antrag_id:\d+>/aenderungsantrag/neu'                                     => 'aenderungsantrag/neu',
				'<veranstaltung_id:[\w_-]+>/antrag/<antrag_id:\d+>/aenderungsantrag/ajaxCalcDiff'                            => 'aenderungsantrag/ajaxCalcDiff',
			),
		),
		'authManager'    => array(
			'class'        => 'CDbAuthManager',
			'connectionID' => 'db',
		),

		'db'             => array(
			"connectionString" => "mysql:host=localhost;dbname=parteitool",
			"emulatePrepare"   => true,
			"username"         => "parteitool",
			"password"         => "strenggeheim",
			"charset"          => "utf8",
			"enableProfiling"  => true
		),
		'errorHandler'   => array(
			// use 'site/error' action to display errors
			'errorAction' => 'site/error',
		),
		'log'            => array(
			'class'  => 'CLogRouter',
			'routes' => array(
				array(
					'class'  => 'CFileLogRoute',
					'levels' => 'error, warning',
				),
				/*
				array(
					'class'=> 'CWebLogRoute',
				),
				*/
			),
		),
		'bootstrap'      => array(
			'class' => 'composer.chris83.yii-bootstrap.components.Bootstrap',
		),
		'datetimepicker' => array(
			'class' => 'ext.datetimepicker.EDateTimePicker',
		),
		'loid'           => array(
			'class' => 'application.extensions.lightopenid.loid',
		),
		/*
	   'clientScript'=>array(
		   'class' => 'CClientScript',
		   'scriptMap' => array(
			   'jquery.js'=>false,
		   ),
		   'coreScriptPosition' => CClientScript::POS_BEGIN,
	   ),
		   */
	),

	'params'     => array(
		'standardVeranstaltung'           => 1,
		'standardVeranstaltungAutoCreate' => true,
		'pdf_logo'                        => 'LOGO_PFAD',
		'kontakt_email'                   => 'EMAILADRESSE',
		'mail_from'                       => 'Antragsgrün <EMAILADRESSE>',
		'font_css'                        => '/css/font.css',
	),
);
