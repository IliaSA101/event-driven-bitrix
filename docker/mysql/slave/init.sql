-- Искусственная задержка, чтобы Master БД успела подняться и инициализироваться
DO SLEEP(10);

-- Настраиваем репликацию с использованием GTID
CHANGE MASTER TO 
    MASTER_HOST='db-master', 
    MASTER_USER='replicator', 
    MASTER_PASSWORD='repl_password', 
    MASTER_USE_GTID=slave_pos;

START SLAVE;
