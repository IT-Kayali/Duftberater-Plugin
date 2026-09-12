<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	array(
		'key' => 'geschlecht',
		'label' => 'Für wen suchst du den Duft?',
		'description' => 'Schritt 1 der Roadmap: Herren, Damen oder Unisex.',
		'label_i18n' => array( 'de' => 'Für wen suchst du den Duft?', 'en' => 'Who is the fragrance for?', 'ar' => 'لمن تبحث عن العطر؟' ),
		'description_i18n' => array( 'de' => 'Schritt 1 der Roadmap: Herren, Damen oder Unisex.', 'en' => 'Step 1: choose men, women or unisex.', 'ar' => 'الخطوة 1: اختر رجال أو نساء أو للجميع.' ),
		'multiple' => 0, 'required' => 1, 'order' => 1,
		'options' => array( 'damen' => 'Damen', 'herren' => 'Herren', 'unisex' => 'Unisex' ),
		'options_i18n' => array(
			'damen' => array( 'de' => 'Damen', 'en' => 'Women', 'ar' => 'نساء' ),
			'herren' => array( 'de' => 'Herren', 'en' => 'Men', 'ar' => 'رجال' ),
			'unisex' => array( 'de' => 'Unisex', 'en' => 'Unisex', 'ar' => 'للجميع' ),
		),
	),
	array(
		'key' => 'jahreszeit',
		'label' => 'Zu welcher Jahreszeit soll der Duft hauptsächlich getragen werden?',
		'description' => 'Schritt 3 der Roadmap: Die Saison steuert Frische, Wärme und Dichte.',
		'label_i18n' => array( 'de' => 'Zu welcher Jahreszeit soll der Duft hauptsächlich getragen werden?', 'en' => 'In which season will the fragrance mainly be worn?', 'ar' => 'في أي فصل سيتم استخدام العطر غالباً؟' ),
		'description_i18n' => array( 'de' => 'Schritt 3 der Roadmap: Die Saison steuert Frische, Wärme und Dichte.', 'en' => 'Step 2: the season influences freshness, warmth and depth.', 'ar' => 'الخطوة 2: الفصل يؤثر على الانتعاش والدفء والعمق.' ),
		'multiple' => 0, 'required' => 1, 'order' => 3,
		'options' => array( 'fruehling' => 'Frühling', 'herbst' => 'Herbst', 'sommer' => 'Sommer', 'winter' => 'Winter' ),
		'options_i18n' => array(
			'fruehling' => array( 'de' => 'Frühling', 'en' => 'Spring', 'ar' => 'الربيع' ),
			'herbst' => array( 'de' => 'Herbst', 'en' => 'Autumn', 'ar' => 'الخريف' ),
			'sommer' => array( 'de' => 'Sommer', 'en' => 'Summer', 'ar' => 'الصيف' ),
			'winter' => array( 'de' => 'Winter', 'en' => 'Winter', 'ar' => 'الشتاء' ),
		),
	),
	array(
		'key' => 'verwendungsbereich',
		'label' => 'Für welchen Gebrauch / Verwendungsbereich suchst du den Duft?',
		'description' => 'Schritt 4 der Roadmap: Erst Hauptsituation wählen, dann führt dich das System je nach Fall zur nächsten passenden Teilfrage.',
		'label_i18n' => array( 'de' => 'Für welchen Gebrauch / Verwendungsbereich suchst du den Duft?', 'en' => 'What occasion or use is the fragrance for?', 'ar' => 'لأي استخدام أو مناسبة تبحث عن العطر؟' ),
		'description_i18n' => array( 'de' => 'Wähle zuerst die Hauptsituation. Danach erzeugt der Berater automatisch die nächste passende Folgefrage.', 'en' => 'First choose the main situation. The advisor will then show the right follow-up question.', 'ar' => 'اختر الحالة الأساسية أولاً، ثم سيعرض المستشار السؤال المناسب التالي.' ),
		'multiple' => 0, 'required' => 1, 'order' => 4,
		'options' => array( 'arbeit' => 'Arbeit', 'ausgehen' => 'Ausgehen und Freizeit', 'buero' => 'Büro und geschlossene Räume', 'date' => 'Date', 'grosse_raeume' => 'große Räumlichkeiten und Öffentlichkeit', 'meeting' => 'Meeting', 'private_anlaesse' => 'private feierliche Anlässe', 'zuhause' => 'Zuhause' ),
		'options_i18n' => array(
			'arbeit' => array( 'de' => 'Arbeit', 'en' => 'Work', 'ar' => 'العمل' ),
			'ausgehen' => array( 'de' => 'Ausgehen und Freizeit', 'en' => 'Going out and leisure', 'ar' => 'الخروج ووقت الفراغ' ),
			'buero' => array( 'de' => 'Büro und geschlossene Räume', 'en' => 'Office and indoor rooms', 'ar' => 'المكتب والأماكن المغلقة' ),
			'date' => array( 'de' => 'Date', 'en' => 'Date', 'ar' => 'موعد' ),
			'grosse_raeume' => array( 'de' => 'große Räumlichkeiten und Öffentlichkeit', 'en' => 'Large spaces and public settings', 'ar' => 'الأماكن الواسعة والعامة' ),
			'meeting' => array( 'de' => 'Meeting', 'en' => 'Meeting', 'ar' => 'اجتماع' ),
			'private_anlaesse' => array( 'de' => 'private feierliche Anlässe', 'en' => 'Private special occasions', 'ar' => 'مناسبات خاصة' ),
			'zuhause' => array( 'de' => 'Zuhause', 'en' => 'At home', 'ar' => 'في المنزل' ),
		),
	),
	array(
		'key' => 'raucher',
		'label' => 'Soll der Duft auch für Raucher geeignet sein?',
		'description' => 'Schritt 5 der Roadmap: Kräftigere Düfte können hier Vorteile haben.',
		'label_i18n' => array( 'de' => 'Soll der Duft auch für Raucher geeignet sein?', 'en' => 'Should the fragrance also suit smokers?', 'ar' => 'هل يجب أن يكون العطر مناسباً للمدخنين؟' ),
		'description_i18n' => array( 'de' => 'Schritt 5 der Roadmap: Kräftigere Düfte können hier Vorteile haben.', 'en' => 'Step 4: stronger fragrances can be useful here.', 'ar' => 'الخطوة 4: العطور الأقوى قد تكون مناسبة هنا.' ),
		'multiple' => 0, 'required' => 1, 'order' => 5,
		'options' => array( 'ja' => 'Ja', 'nein' => 'Nein' ),
		'options_i18n' => array( 'ja' => array( 'de' => 'Ja', 'en' => 'Yes', 'ar' => 'نعم' ), 'nein' => array( 'de' => 'Nein', 'en' => 'No', 'ar' => 'لا' ) ),
	),
	array(
		'key' => 'duftrichtungen',
		'label' => 'Welche Duftrichtungen bevorzugst du?',
		'description' => 'Schritt 6 der Roadmap: Mehrfachauswahl ist erlaubt und wirkt stark positiv ins Matching.',
		'label_i18n' => array( 'de' => 'Welche Duftrichtungen bevorzugst du?', 'en' => 'Which fragrance families do you prefer?', 'ar' => 'ما هي العائلات العطرية التي تفضلها؟' ),
		'description_i18n' => array( 'de' => 'Mehrfachauswahl ist erlaubt und wirkt stark positiv ins Matching.', 'en' => 'Multiple selection is allowed and strongly influences the match.', 'ar' => 'يمكن اختيار أكثر من إجابة، وهذا يؤثر بقوة على النتيجة.' ),
		'multiple' => 1, 'required' => 1, 'order' => 6,
		'options' => array( 'amber' => 'Amberiert', 'aquatisch' => 'Aquatisch', 'blumig' => 'Blumig', 'fruchtig' => 'Fruchtig', 'gourmand' => 'Gourmandig', 'gruen' => 'Grün', 'holzig' => 'Holzig', 'ledrig' => 'Ledrig', 'moschus' => 'Moschus', 'oud' => 'Oud', 'wuerzig' => 'Würzig', 'zitrisch' => 'Zitrisch' ),
		'options_i18n' => array(
			'amber' => array( 'de' => 'Amberiert', 'en' => 'Amber', 'ar' => 'عنبر' ), 'aquatisch' => array( 'de' => 'Aquatisch', 'en' => 'Aquatic', 'ar' => 'مائي' ), 'blumig' => array( 'de' => 'Blumig', 'en' => 'Floral', 'ar' => 'زهري' ), 'fruchtig' => array( 'de' => 'Fruchtig', 'en' => 'Fruity', 'ar' => 'فاكهي' ), 'gourmand' => array( 'de' => 'Gourmandig', 'en' => 'Gourmand', 'ar' => 'حلو / غورماند' ), 'gruen' => array( 'de' => 'Grün', 'en' => 'Green', 'ar' => 'أخضر' ), 'holzig' => array( 'de' => 'Holzig', 'en' => 'Woody', 'ar' => 'خشبي' ), 'ledrig' => array( 'de' => 'Ledrig', 'en' => 'Leather', 'ar' => 'جلدي' ), 'moschus' => array( 'de' => 'Moschus', 'en' => 'Musk', 'ar' => 'مسك' ), 'oud' => array( 'de' => 'Oud', 'en' => 'Oud', 'ar' => 'عود' ), 'wuerzig' => array( 'de' => 'Würzig', 'en' => 'Spicy', 'ar' => 'حار / توابلي' ), 'zitrisch' => array( 'de' => 'Zitrisch', 'en' => 'Citrus', 'ar' => 'حمضي' ) ),
	),
	array(
		'key' => 'alter',
		'label' => 'Welche Altersgruppe passt am ehesten?',
		'description' => 'Schritt 2 der Roadmap: Diese Angabe unterstützt die Feinabstimmung.',
		'label_i18n' => array( 'de' => 'Welche Altersgruppe passt am ehesten?', 'en' => 'Which age group fits best?', 'ar' => 'أي فئة عمرية تناسب أكثر؟' ),
		'description_i18n' => array( 'de' => 'Diese Angabe unterstützt die Feinabstimmung.', 'en' => 'This helps fine-tune the recommendation.', 'ar' => 'هذا يساعد على تحسين الترشيح.' ),
		'multiple' => 0, 'required' => 1, 'order' => 2,
		'options' => array( '10_20' => '10 bis 20', '20_30' => '20 bis 30', '30_40' => '30 bis 40', 'ueber_40' => 'über 40' ),
		'options_i18n' => array( '10_20' => array( 'de' => '10 bis 20', 'en' => '10 to 20', 'ar' => '10 إلى 20' ), '20_30' => array( 'de' => '20 bis 30', 'en' => '20 to 30', 'ar' => '20 إلى 30' ), '30_40' => array( 'de' => '30 bis 40', 'en' => '30 to 40', 'ar' => '30 إلى 40' ), 'ueber_40' => array( 'de' => 'über 40', 'en' => 'over 40', 'ar' => 'أكثر من 40' ) ),
	),
	array(
		'key' => 'duftnoten',
		'label' => 'Welche Duftnoten sollen zum Duft passen?',
		'description' => '',
		'label_i18n' => array( 'de' => 'Welche Duftnoten sollen zum Duft passen?', 'en' => 'Which notes should the fragrance contain?', 'ar' => 'ما هي النوتات التي تريد أن يحتويها العطر؟' ),
		'description_i18n' => array( 'de' => '', 'en' => '', 'ar' => '' ),
		'multiple' => 1, 'required' => 1, 'order' => 7,
		'options' => array( 'animalische_noten' => 'Animalische Noten', 'aromatische_noten' => 'Aromatische Noten', 'blumige_noten' => 'Blumige Noten', 'erdige_noten' => 'Erdige Noten', 'fruchtige_noten' => 'Fruchtige Noten', 'gewuerznoten' => 'Gewürznoten', 'harze_balsame' => 'Harze / Balsame', 'holznoten' => 'Holznoten', 'suesse_gourmand' => 'Süße / Gourmand Noten', 'zitrusnoten' => 'Zitrusnoten' ),
		'options_i18n' => array(
			'animalische_noten' => array( 'de' => 'Animalische Noten', 'en' => 'Animalic notes', 'ar' => 'نوتات حيوانية' ), 'aromatische_noten' => array( 'de' => 'Aromatische Noten', 'en' => 'Aromatic notes', 'ar' => 'نوتات عطرية عشبية' ), 'blumige_noten' => array( 'de' => 'Blumige Noten', 'en' => 'Floral notes', 'ar' => 'نوتات زهرية' ), 'erdige_noten' => array( 'de' => 'Erdige Noten', 'en' => 'Earthy notes', 'ar' => 'نوتات ترابية' ), 'fruchtige_noten' => array( 'de' => 'Fruchtige Noten', 'en' => 'Fruity notes', 'ar' => 'نوتات فاكهية' ), 'gewuerznoten' => array( 'de' => 'Gewürznoten', 'en' => 'Spicy notes', 'ar' => 'نوتات توابل' ), 'harze_balsame' => array( 'de' => 'Harze / Balsame', 'en' => 'Resins / balsams', 'ar' => 'راتنجات / بلسم' ), 'holznoten' => array( 'de' => 'Holznoten', 'en' => 'Woody notes', 'ar' => 'نوتات خشبية' ), 'suesse_gourmand' => array( 'de' => 'Süße / Gourmand Noten', 'en' => 'Sweet / gourmand notes', 'ar' => 'نوتات حلوة / غورماند' ), 'zitrusnoten' => array( 'de' => 'Zitrusnoten', 'en' => 'Citrus notes', 'ar' => 'نوتات حمضية' ) ),
	),
);
