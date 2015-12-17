//- основная таблица содержит слова (id, слово, часть_речи_id), на нее ссылаются таблицы: 
//- словоформы (id, слово_id, словоформа_тип_id, словарь_id [источник], словоформа)
//- словарные статьи (id, слово_id, словарь_id, значение)
//- показатели (id, слово_id, показатель_тип_id, словарь_id [источник], показатель(float)) [например частотные показатели]

// part of speech = pos
// лексема (словоформа) = lexeme

CREATE TABLE `word` (
    `id` int NOT NULL AUTO_INCREMENT, 
    `pos_id` int DEFAULT NULL COMMENT 'Часть речи' , 
    `name` varchar(100) NOT NULL,
    `word_source_id` int NOT NULL,
    `ts` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`))
COMMENT='Основная таблица словаря';

CREATE TABLE `pos` ( 
    `id` int NOT NULL AUTO_INCREMENT,
    `lang_id` int NOT NULL COMMENT 'Язык',
    `name` varchar(150) COMMENT 'Наименование части речи на языке',
    PRIMARY KEY (`id`)
) COMMENT='Справочник: Часть речи (существительное, прилагательное, глагол и тд)';

CREATE TABLE `word_source` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL DEFAULT '' COMMENT 'Название словаря',
  `comment` tinytext,
  `user_id` int(11) NOT NULL,
  `ts` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `url` varchar(500) DEFAULT NULL,
  `lang_id` int(11) NOT NULL DEFAULT '1',
  `ws_status_id` int(11) NOT NULL DEFAULT '1' COMMENT 'статус обработки словаря',
  PRIMARY KEY (`id`)
) COMMENT 'Словари - источники';

CREATE TABLE `ws_status` (
    `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
    `name` varchar(30) DEFAULT '',
    PRIMARY KEY (`id`))
COMMENT='Справочник: Статусы обработки словарей';

CREATE TABLE `lexeme` (
    `id` int NOT NULL AUTO_INCREMENT,
    `word_id` int COMMENT 'слово',
    `lex_type_id` int NOT NULL COMMENT 'Тип словоформы',
    `name` varchar(150) NOT NULL COMMENT 'Собственно словоформа', 
    `word_source_id` int NOT NULL COMMENT 'Словарь (язык и пользователь)',
    `ts` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`))
COMMENT = 'Словоформы';

CREATE TABLE lex_type (
    id int NOT NULL AUTO_INCREMENT,
    lang_id int NOT NULL COMMENT 'Язык',
    name varchar(150) COMMENT 'тип словоформы на языке', 
    PRIMARY KEY (`id`))
COMMENT = 'Справочник: тип словоформы (например глагол в форме )';
 
CREATE TABLE vocabulary (
    id int NOT NULL AUTO_INCREMENT,
    `word_id` int COMMENT 'слово',
    `name` tinytext COMMENT 'собственно словарная статья' ,
    `word_source_id` int NOT NULL COMMENT 'Словарь (язык и пользователь)',
    `ts` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`))
COMMENT = 'словарная статья - описание слова, перевод?';

CREATE TABLE word_factor (
    id int NOT NULL AUTO_INCREMENT,
    `word_id` int COMMENT 'слово',
    `wf_type_id` int NOT NULL COMMENT 'тип показателя' ,
    `factor` float COMMENT 'собственно показатель' ,
    `word_source_id` int NOT NULL COMMENT 'Словарь (язык и пользователь)',
    `ts` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`))
COMMENT = 'показатели слова (например: частотность, употребимость и тд)';

CREATE TABLE wf_type (
    id int NOT NULL AUTO_INCREMENT,
    lang_id int NOT NULL COMMENT 'Язык',
    name varchar(150) COMMENT 'тип показателя на языке', 
    PRIMARY KEY (`id`))
COMMENT = 'Справочник: тип показателя (например: частотность)';



