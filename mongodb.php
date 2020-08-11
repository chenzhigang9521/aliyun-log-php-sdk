<?php

require_once realpath(dirname(__FILE__)) . '/vendor/autoload.php';
use MongoDB\Client;
use MongoDB\Model\BSONDocument;
use MongoDB\Operation\Aggregate;

class Mongodb
{
    private static $host = '127.0.0.1';
    private static $port = '27017';
    private static $database = 'test';
    private static $conn;

    public function __construct()
    {
        self::init();
    }

    public static function init()
    {
        self::$conn = new Client("mongodb://" . self::$host . ":" . self::$port);
        $connection = self::$conn;
        $database = self::$database;
        self::$conn = $connection->$database;
    }

    public static function getInstance()
    {
        if (!(self::$conn instanceof self)) {
            self::init();
        }
        //return self::$conn->mydb;
        return self::$conn;
    }

    private function __clone()
    {
        trigger_error('Clone is not allowed');
    }//禁止克隆

    public function getOne($table, $where = [])
    {
        try {
            $connection = self::$conn;
            $collection = $connection->$table;
            isset($where['_id']) && $where['_id'] = new \MongoDB\BSON\ObjectId($where['_id']);
            $document = $collection->findOne($where);
            if (empty($document)) {
                return [];
            }
            $document = $document->bsonSerialize();
            $document = json_decode(json_encode($document), true);
            return $document;
        } catch (Exception $e) {
            //记录错误
        }
        return false;
    }

    public function insert($table, $insert_data)
    {
        try {
            $connection = self::$conn;
            $collection = $connection->$table;
            $insertManyResult = $collection->insertOne($insert_data);
            return $insertManyResult->getInsertedCount();
        } catch (Exception $e) {
            //记录错误
        }
        return false;
    }

    public function update($table, $where, $update_data)
    {
        try {
            $connection = self::$conn;
            $collection = $connection->$table;
            $update_data = ['$set' => $update_data];
            $updateManyResult = $collection->updateMany($where, $update_data);
            return $updateManyResult->getModifiedCount();
        } catch (Exception $e) {
            //记录错误
        }
        return false;
    }

    public function aggregate($table, $matches = [], $group = [], $sort = [], $project = [], $offset = 0, $limit = 20)
    {
        $params = [
            ['$match' => empty($matches) ? new stdclass() : $matches],
            ['$group' => empty($group) ? new stdclass() : $group],
            ['$sort' => empty($sort) ? new stdclass() : $sort],
            ['$project' => empty($project) ? new stdclass() : $project],
            ['$skip' => $offset],
            ['$limit' => $limit],
        ];
        try {
            $connection = self::$conn;
            $collection = $connection->$table;
            $document = $collection->aggregate($params);
            if (empty($document)) {
                return [];
            }
            $new_document = [];
            foreach ($document as $value) {
                $value = json_decode(json_encode($value), true);
                $new_document[] = $value;
            }
            return $new_document;
        } catch (Exception $e) {
            echo $e->getMessage();
            // 记录错误
        }
        return false;
    }
}
