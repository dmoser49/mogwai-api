<?php

/**
 * Index a ton of data from mogwaid into MySQL
 */

if (php_sapi_name() !== 'cli') {
    die("Command line only" . PHP_EOL);
}

require_once('.credentials.php');
require_once('rpcclient.php');

$rpc = new RPCClient($rpc_credentials['user'], $rpc_credentials['password'], $rpc_credentials['host'], $rpc_credentials['port'])
    or die("Unable to instantiate RPCClient" . PHP_EOL);

$mysqli = new mysqli($db_credentials['host'], $db_credentials['user'], $db_credentials['password'], $db_credentials['name']);

if (!$mysqli || $mysqli->connect_errno) {
    die("Could not instantiate mysqli" . PHP_EOL);
}

// check that tables exist, or create them
create_tables();

// check the highest block count and index data from mogwaid if there is more
$blockcount = $rpc->getblockcount();
$blockcount_db = get_db_blockcount() + 1;

echo "blockcount: $blockcount vs $blockcount_db" . PHP_EOL;

while ($blockcount_db < $blockcount) {
    if ($blockcount_db < 1) {
        $hash = $rpc->getblockhash(0);
    }
    else {
        $hash = $rpc->getblockhash($blockcount_db);
    }

    echo "$blockcount_db: $hash" . PHP_EOL;

    if (empty($hash)) {
        die("Panic!  Received empty blockhash at height $blockcount_db" . PHP_EOL);
    }

    $block = $rpc->getblock($hash);
    // print_r($block);

    foreach ($block["tx"] as $tx) {
        $tx = $mysqli->real_escape_string($tx);

        $rawtx = $rpc->getrawtransaction($tx);
        if ($rawtx) {
            $transaction = $rpc->decoderawtransaction($rawtx);

            $vin = $transaction['vin'];
            $vout = $transaction['vout'];

            foreach ($vin as $input) {
                if (@$input['scriptPubKey']['addresses']) {
                    foreach ($input['scriptPubKey']['addresses'] as $address) {
                        $address = $mysqli->real_escape_string($address);
                        // echo "INSERT IGNORE INTO `blocks_addresses` (`block`, `address`) VALUES ($blockcount_db, '$address')" . PHP_EOL;
                        $mysqli->query("INSERT IGNORE INTO `blocks_addresses` (`block`, `address`) VALUES ($blockcount_db, '$address')") or die ("invalid query" . PHP_EOL);
                        // echo "INSERT IGNORE INTO `transactions_addresses` (`transaction`, `address`) VALUES ('$tx', '$address')" . PHP_EOL;
                        $mysqli->query("INSERT IGNORE INTO `transactions_addresses` (`transaction`, `address`) VALUES ('$tx', '$address')") or die("invalid query" . PHP_EOL);
                    }
                }
            }

            foreach ($vout as $input) {
                if (@$input['scriptPubKey']['addresses']) {
                    foreach ($input['scriptPubKey']['addresses'] as $address) {
                        $address = $mysqli->real_escape_string($address);
                        // echo "INSERT IGNORE INTO `blocks_addresses` (`block`, `address`) VALUES ($blockcount_db, '$address')" . PHP_EOL;
                        $mysqli->query("INSERT IGNORE INTO `blocks_addresses` (`block`, `address`) VALUES ($blockcount_db, '$address')") or die ("invalid query" . PHP_EOL);
                        // echo "INSERT IGNORE INTO `transactions_addresses` (`transaction`, `address`) VALUES ('$tx', '$address')" . PHP_EOL;
                        $mysqli->query("INSERT IGNORE INTO `transactions_addresses` (`transaction`, `address`) VALUES ('$tx', '$address')") or die("invalid query" . PHP_EOL);
                    }
                }
            }

            // print_r($transaction);
        }
        else {
            echo "Could not get raw transaction for $blockcount_db : $tx" . PHP_EOL;
        }
    }

    $blockcount_db = intval($block['height']) + 1;


    //if ($blockcount_db > 3) break;
}









//// functions

function create_tables($tablename = null) {
    global $mysqli;

    if (!$mysqli->query('select 1 from `blocks_addresses` LIMIT 1')) {
        $query = "CREATE TABLE IF NOT EXISTS `blocks_addresses` (
              `block` int(11) unsigned NOT NULL,
              `address` char(34) NOT NULL DEFAULT '',
              UNIQUE KEY `ix_block_address` (`block`,`address`),
              KEY `ix_block` (`block`),
              KEY `ix_address` (`address`)
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1;
        ";

        $mysqli->real_query($query);
        echo "Created table `block_addresses`" . PHP_EOL;
    }

    if (!$mysqli->query('select 1 from `transactions_addresses` LIMIT 1')) {
        $query = "CREATE TABLE IF NOT EXISTS `transactions_addresses` (
              `transaction` char(64) NOT NULL DEFAULT '',
              `address` char(34) NOT NULL DEFAULT '',
              UNIQUE KEY `ix_transaction_address` (`transaction`,`address`),
              KEY `ix_transaction` (`transaction`),
              KEY `ix_address` (`address`)
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1;
        ";

        $mysqli->real_query($query);
        echo "Created table `transactions_addresses`" . PHP_EOL;
    }
}

function get_db_blockcount() {
    global $mysqli;

    $res = $mysqli->query("SELECT max(block) AS mx FROM `blocks_addresses`");
    if ($res->num_rows) {
        $row = $res->fetch_row();
        return intval($row[0]);
    }

    return -1;
}
