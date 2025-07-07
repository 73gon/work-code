<?php

namespace dashboard\MyWidgets\NewsBox;

use JobRouter\Api\Dashboard\v1\Widget;


class NewsBox extends Widget
{

    public function getTitle()
    {
        return 'Mitteilungen';
    }

    public function getCategory()
    {
        return 'administration';
    }

    public function getData()
    {
        return [
            'news' => json_encode($this->getNews()),
            'noEntries' => 'Keine Einträge vorhanden',
            'currentAuthor' => $this->getCurrentUsername(),
            'canEdit' => $this->getUser()->getUsername() == 'admin' || $this->getUser()->isInJobFunction('NewsBoxAdmin')
        ];
    }

    public function getDimensions()
    {
        return [
            'minHeight' => 5,
            'minWidth' => 3,
            'maxHeight' => 10,
            'maxWidth' => 6,
        ];
    }

    public function isMandatory()
    {
        return true;
    }

    private function getNews()
    {
        if (!$this->tableExists('newsBoxWidget')) {
            $this->createNewsBoxTable();
        }

        // First, delete any news items that have reached their deleteDate
        $this->deleteExpiredNews();

        $jobDB = $this->getJobDB();
        $sql = "SELECT id, author, date, title, message, lastEditBy, lastEditDate, deleteDate FROM newsBoxWidget ORDER BY id DESC";

        $result = $jobDB->query($sql);

        if ($result === false) {
            return [];
        }

        $newsData = [];
        $rows = $jobDB->fetchAll($result);

        foreach ($rows as $row) {
            $newsData[] = [
                'id' => (int)$row['id'],
                'author' => $row['author'],
                'date' => $row['date'],
                'title' => $row['title'],
                'message' => $row['message'],
                'lastEditBy' => $row['lastEditBy'],
                'lastEditDate' => $row['lastEditDate'],
                'deleteDate' => $row['deleteDate']
            ];
        }

        return $newsData;
    }

    private function deleteExpiredNews()
    {
        $jobDB = $this->getJobDB();
        $currentDate = date('Y-m-d');

        // Delete news items where deleteDate is set (not null) and is today or in the past
        $sql = "DELETE FROM newsBoxWidget WHERE deleteDate IS NOT NULL AND deleteDate <= " . $jobDB->quote($currentDate);
        $jobDB->exec($sql);
    }

    private function tableExists($tableName)
    {
        $jobDB = $this->getJobDB();
        $dbType = $this->getDatabaseType();

        if ($dbType === 'mysql') {
            $sql = "SELECT COUNT(*) as count FROM information_schema.tables
                    WHERE table_schema = DATABASE() AND table_name = " . $jobDB->quote($tableName);
        } else { // mssql
            $sql = "SELECT COUNT(*) as count FROM information_schema.tables
                    WHERE table_name = " . $jobDB->quote($tableName);
        }

        $result = $jobDB->query($sql);
        if ($result === false) {
            return false;
        }

        $row = $jobDB->fetchRow($result);
        return $row['count'] > 0;
    }

    private function createNewsBoxTable()
    {
        $jobDB = $this->getJobDB();
        $dbType = $this->getDatabaseType();

        if ($dbType === 'mysql') {
            $sql = "CREATE TABLE newsBoxWidget (
                id INT AUTO_INCREMENT PRIMARY KEY,
                author VARCHAR(255) NOT NULL,
                date DATE NOT NULL,
                title VARCHAR(500) NOT NULL,
                message TEXT NOT NULL,
                lastEditBy VARCHAR(255) NULL,
                lastEditDate DATE NULL,
                deleteDate DATE NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        } else { // mssql
            $sql = "CREATE TABLE newsBoxWidget (
                id INT IDENTITY(1,1) PRIMARY KEY,
                author NVARCHAR(255) NOT NULL,
                date DATE NOT NULL,
                title NVARCHAR(500) NOT NULL,
                message NTEXT NOT NULL,
                lastEditBy NVARCHAR(255) NULL,
                lastEditDate DATE NULL,
                deleteDate DATE NULL
            )";
        }

        $jobDB->exec($sql);
    }

    private function getDatabaseType()
    {
        $jobDB = $this->getJobDB();

        // Try to execute a MySQL-specific query
        try {
            $result = $jobDB->query("SELECT DATABASE()");
            if ($result !== false) {
                return 'mysql';
            }
        } catch (\Exception $e) {
            // MySQL query failed, likely MSSQL
        }

        // Try to execute an MSSQL-specific query
        try {
            $result = $jobDB->query("SELECT DB_NAME()");
            if ($result !== false) {
                return 'mssql';
            }
        } catch (\Exception $e) {
            // MSSQL query failed
        }

        // Default fallback
        return 'mysql';
    }

    public function isAuthorized()
    {
        // nur dem admin und Usern, die in der Rolle 'Mitteilungempänger' sind wird das Widget angeboten!
        return $this->getUser()->getUsername() == 'admin' || $this->getUser()->isInJobFunction('NewsBoxUser');
    }
}
