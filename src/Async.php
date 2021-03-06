<?php

/**
 * Created by PhpStorm.
 * User: Jenner
 * Date: 2015/7/20
 * Time: 16:04
 */

namespace Jenner\Mysql;

class Async
{
    protected $links;

    public function attach($config, $query)
    {
        $link = mysqli_connect(
            $config['host'],
            $config['user'],
            $config['password'],
            $config['database'],
            $config['port']
        );

        if ($link === false) {
            throw new \RuntimeException(mysqli_connect_error(), mysqli_connect_errno());
        }
        $link->query($query, MYSQLI_ASYNC);

        $this->links[] = $link;
    }

    public function execute()
    {
        $collect = array();

        $link_count = count($this->links);
        $processed = 0;
        do {
            $links = $errors = $reject = array();
            foreach ($this->links as $link) {
                $links[] = $errors[] = $reject[] = $link;
            }
            if (!mysqli_poll($links, $errors, $reject, 0, 1000)) {
                continue;
            }
            for ($i = 0; $i < $link_count; $i++) {
                $link = $this->links[$i];
                if(mysqli_errno($link)){
                    throw new \RuntimeException(mysqli_error($link), mysqli_errno($link));
                }

                if ($result = $link->reap_async_query()) {
                    if (is_object($result)) {
                        $temp = array();
                        while (($row = $result->fetch_assoc()) && $temp[] = $row) ;
                        $collect[$i] = $temp;
                        mysqli_free_result($result);
                    } else {
                        $collect[$i] = $result;
                    }
                }
                $processed++;
            }
        } while ($processed < $link_count);

        return $collect;
    }
}
