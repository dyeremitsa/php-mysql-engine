CREATE TABLE `information_schema.tables`
(
    `TABLE_CATALOG`   varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
    `TABLE_SCHEMA`    varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
    `TABLE_NAME`      varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
    `TABLE_TYPE`      enum('BASE TABLE','VIEW','SYSTEM VIEW') CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
    `ENGINE`          varchar(64) CHARACTER SET utf8  DEFAULT NULL,
    `VERSION`         int                             DEFAULT NULL,
    `ROW_FORMAT`      enum('Fixed','Dynamic','Compressed','Redundant','Compact','Paged') CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
    `TABLE_ROWS`      bigint unsigned DEFAULT NULL,
    `AVG_ROW_LENGTH`  bigint unsigned DEFAULT NULL,
    `DATA_LENGTH`     bigint unsigned DEFAULT NULL,
    `MAX_DATA_LENGTH` bigint unsigned DEFAULT NULL,
    `INDEX_LENGTH`    bigint unsigned DEFAULT NULL,
    `DATA_FREE`       bigint unsigned DEFAULT NULL,
    `AUTO_INCREMENT`  bigint unsigned DEFAULT NULL,
    `CREATE_TIME`     timestamp                                       NOT NULL,
    `UPDATE_TIME`     datetime                        DEFAULT NULL,
    `CHECK_TIME`      datetime                        DEFAULT NULL,
    `TABLE_COLLATION` varchar(64) CHARACTER SET utf8,
    `CHECKSUM`        bigint                          DEFAULT NULL,
    `CREATE_OPTIONS`  varchar(256) CHARACTER SET utf8 DEFAULT NULL,
    `TABLE_COMMENT`   text CHARACTER SET utf8
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin