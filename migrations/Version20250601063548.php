<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250601063548 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE `character` (id INT AUTO_INCREMENT NOT NULL, session_id INT NOT NULL, name VARCHAR(255) NOT NULL, rolls INT NOT NULL, average DOUBLE PRECISION NOT NULL, total_value INT NOT NULL, INDEX IDX_937AB034613FECDF (session_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE chatlog_file (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, filename VARCHAR(255) NOT NULL, uploaded_at DATETIME NOT NULL, modified_at DATETIME DEFAULT NULL, INDEX IDX_A5B7F571A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE roll (id INT AUTO_INCREMENT NOT NULL, character_id INT NOT NULL, session_id INT NOT NULL, dice_type VARCHAR(32) NOT NULL, num_dice INT NOT NULL, bonus INT DEFAULT NULL, total_value INT NOT NULL, actual_roll INT NOT NULL, is_advantage TINYINT(1) NOT NULL, is_disadvantage TINYINT(1) NOT NULL, dropped_value INT DEFAULT NULL, skill VARCHAR(255) DEFAULT NULL, INDEX IDX_2EB532CE1136BE75 (character_id), INDEX IDX_2EB532CE613FECDF (session_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE session (id INT AUTO_INCREMENT NOT NULL, chatlog_file_id INT NOT NULL, date DATE NOT NULL, time TIME NOT NULL, total_rolls INT NOT NULL, average DOUBLE PRECISION NOT NULL, INDEX IDX_D044D5D4FBCF425 (chatlog_file_id), UNIQUE INDEX unique_session (date, time, chatlog_file_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE skill (id INT AUTO_INCREMENT NOT NULL, character_id INT NOT NULL, session_id INT NOT NULL, name VARCHAR(255) NOT NULL, count INT NOT NULL, INDEX IDX_5E3DE4771136BE75 (character_id), INDEX IDX_5E3DE477613FECDF (session_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, username VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_8D93D649F85E0677 (username), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE `character` ADD CONSTRAINT FK_937AB034613FECDF FOREIGN KEY (session_id) REFERENCES session (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE chatlog_file ADD CONSTRAINT FK_A5B7F571A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE roll ADD CONSTRAINT FK_2EB532CE1136BE75 FOREIGN KEY (character_id) REFERENCES `character` (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE roll ADD CONSTRAINT FK_2EB532CE613FECDF FOREIGN KEY (session_id) REFERENCES session (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE session ADD CONSTRAINT FK_D044D5D4FBCF425 FOREIGN KEY (chatlog_file_id) REFERENCES chatlog_file (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE skill ADD CONSTRAINT FK_5E3DE4771136BE75 FOREIGN KEY (character_id) REFERENCES `character` (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE skill ADD CONSTRAINT FK_5E3DE477613FECDF FOREIGN KEY (session_id) REFERENCES session (id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE `character` DROP FOREIGN KEY FK_937AB034613FECDF
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE chatlog_file DROP FOREIGN KEY FK_A5B7F571A76ED395
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE roll DROP FOREIGN KEY FK_2EB532CE1136BE75
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE roll DROP FOREIGN KEY FK_2EB532CE613FECDF
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE session DROP FOREIGN KEY FK_D044D5D4FBCF425
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE skill DROP FOREIGN KEY FK_5E3DE4771136BE75
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE skill DROP FOREIGN KEY FK_5E3DE477613FECDF
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE `character`
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE chatlog_file
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE roll
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE session
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE skill
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE user
        SQL);
    }
}
