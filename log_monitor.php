<?php

require_once realpath(dirname(__FILE__) . '/Log_Autoload.php');
require_once 'mongodb.php';

class Log_monitor
{
    private static $endpoint = 'cn-beijing.log.aliyuncs.com';
    private static $logstore = 'cmn_request';
    private static $project = 'cmn';
    private static $accessKeyId = 'LTAI4GDXWzkp71L5dchyVPHV';
    private static $accessKey = 'TaNnygLqbWGVvCz1gefmpmtEyjtjeE';
    private static $token = '';
    private static $log_client;
    private static $mongodb_connection;
    private static $collection = 'monitor';

    public function __construct()
    {
        self::$log_client = new Aliyun_Log_Client(self::$endpoint, self::$accessKeyId, self::$accessKey, self::$token);
        self::$mongodb_connection = new Mongodb();
    }

    public function logMonitor()
    {
        $start_time = time() - 1 * 60 * 60;
        $end_time = time();
        $offset = 0;
        $limit = 100;
        do {
            $query = "* | select (date_format(cast(receive_time as bigint), '%Y-%m-%d %H:%i')) as time, regexp_extract(uri, '(\S+.\?)|(\S+)') as new_uri, count(*) as cnt, count(distinct ip) as ip_num, avg(consume) / 100 as consume from log where uri != '' group by time, new_uri order by cnt, new_uri, time desc limit $offset, $limit";
            $log_list = $this->getLogs($query, $start_time, $end_time);
            if (empty($log_list)) {
                break;
            }
            $offset += $limit;
            foreach ($log_list as $log) {
                $where = [
                    'time' => $log['time'],
                    'new_uri' => $log['new_uri'], 
                ];
                $log_info = self::$mongodb_connection->getOne(self::$collection, $where);
                if (empty ($log_info)) {
                    echo 'insert-' . self::$mongodb_connection->insert(self::$collection, $log) . "\n";
                    continue;
                }
                echo 'update-' . self::$mongodb_connection->update(self::$collection, $where, $log) . "\n";
            }
        } while (true);
    }

    public function getLogs($query = '', $start_time, $end_time)
    {
        $topic = '';
        $request = new Aliyun_Log_Models_GetLogsRequest(self::$project, self::$logstore, $start_time, $end_time, $topic, $query);
        try {
            $response = self::$log_client->getLogs($request);
            $log_list = [];
            foreach ($response -> getLogs() as $log) {
                $log_list[] = $log -> getContents();
            }
            return $log_list;
        } catch (Aliyun_Log_Exception $ex) {
            $this->logVarDump($ex);
        } catch (Exception $ex) {
            $this->logVarDump($ex);
        }
    }

    public function logVarDump($expression)
    {
        print "<br>loginfo begin = ".get_class($expression)."<br>";
        var_dump($expression);
        print "<br>loginfo end<br>";
    }

    public function getEveryMinuteStatisticsInfo($start_time, $end_time, $offset, $limit)
    {
        $matches = [];
        !empty($start_time) && $matches['time']['$gte'] = $start_time;
        !empty($end_time) && $matches['time']['$lte'] = $end_time;
        $group = [
            '_id' => ['time' => '$time'], 
            'total' => ['$sum' => 1],
        ];
        $sort = ['_id.time' => 1];
        $project = ['time' => 1, 'total' => 1];
        $log_info = self::$mongodb_connection->aggregate(self::$collection, $matches, $group, $sort, $project, $offset, $limit);
        return $log_info;
    }

    public function getEveryMinuteUriStaticsInfo($start_time, $end_time, $offset, $limit)
    {
        $matches = [];
        !empty($start_time) && $matches['time']['$gte'] = $start_time;
        !empty($end_time) && $matches['time']['$lte'] = $end_time;
        $group = [
            '_id' => ['date' => '$time', 'new_uri' => '$new_uri'], 
            'total' => ['$sum' => '$cnt'],
        ];
        $sort = ['_id.date' => 1];
        $project = ['time' => 1, 'new_uri' => 1, 'total' => 1];
        $log_info = self::$mongodb_connection->aggregate(self::$collection, $matches, $group, $sort, $project, $offset, $limit);
        return $log_info;

    }
}

$test = new Log_monitor();
$test->logMonitor();
//$log_info = $test->getEveryMinuteUriStaticsInfo('', '', 0, 20);
//echo json_encode($log_info);
