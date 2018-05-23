<?php

$defaultalgo = user()->getState('yaamp-algo');

echo "<div class='main-left-box'>";
echo "<div class='main-left-title'>Pool Status</div>";
echo "<div class='main-left-inner'>";

showTableSorter('maintable1', "{
	tableClass: 'dataGrid2',
	textExtraction: {
		4: function(node, table, n) { return $(node).attr('data'); },
		8: function(node, table, n) { return $(node).attr('data'); }
	}
}");

echo <<<END
<thead>
<tr>
<th>Algo</th>
<th data-sorter="numeric" align="right">Port</th>
<th data-sorter="numeric" align="right">Coins</th>
<th data-sorter="numeric" align="right">Miners</th>
<th data-sorter="numeric" align="right">Hashrate</th>
<th data-sorter="currency" align="right">Fees**</th>
<th data-sorter="currency" class="estimate" align="right">Current<br>Estimate</th>
<!--<th data-sorter="currency" >Norm</th>-->
<th data-sorter="currency" class="estimate" align="right">24 Hours<br>Estimated</th>
<th data-sorter="currency"align="right">24 Hours<br>Actual***</th>
</tr>
</thead>
END;

$best_algo = '';
$best_norm = 0;

$algos = array();
foreach (yaamp_get_algos() as $algo) {
    $algo_norm = yaamp_get_algo_norm($algo);

    $price = controller()->memcache->get_database_scalar("current_price-$algo",
        "select price from hashrate where algo=:algo order by time desc limit 1", array(':algo' => $algo));

    $norm = $price * $algo_norm;
    $norm = take_yaamp_fee($norm, $algo);

    $algos[] = array($norm, $algo);

    if ($norm > $best_norm) {
        $best_norm = $norm;
        $best_algo = $algo;
    }
}

function cmp($a, $b)
{
    return $a[0] < $b[0];
}

usort($algos, 'cmp');

$total_coins = 0;
$total_miners = 0;

$showestimates = false;

echo "<tbody>";
foreach ($algos as $item) {
    $norm = $item[0];
    $algo = $item[1];

    $coinsym = '';
    $coins = getdbocount('db_coins', "enable and visible and auto_ready and algo=:algo", array(':algo' => $algo));

    //$coinsList = [];

//   if ($coins == 1) {
//        // If we only mine one coin, show it...
//        $coin = getdbosql('db_coins', "enable and visible and auto_ready and algo=:algo", array(':algo'=>$algo));
//        $coinsym = empty($coin->symbol2) ? $coin->symbol : $coin->symbol2;
//        $coinsym = '<span title="'.$coin->name.'">'.$coinsym.'</a>';
//    } else {
//        $coinsList = getdbolist('db_coins', "algo=:algo and enable and visible", array(':algo'=>$algo));
//        foreach ($coinsList as $itemCoin) {
//            $stratumItem = getdbosql("db_stratums", "symbol=:symbol", array(':symbol'=>$itemCoin->symbol));
//            echo "$itemCoin->symbol  $itemCoin->name  $stratumItem->port  " ;
//        }
//    }

    if (!$coins) continue;

    $workers = getdbocount('db_workers', "algo=:algo", array(':algo' => $algo));

    $hashrate = controller()->memcache->get_database_scalar("current_hashrate-$algo",
        "select hashrate from hashrate where algo=:algo order by time desc limit 1", array(':algo' => $algo));
    $hashrate_sfx = $hashrate ? Itoa2($hashrate) . 'h/s' : '-';

    $price = controller()->memcache->get_database_scalar("current_price-$algo",
        "select price from hashrate where algo=:algo order by time desc limit 1", array(':algo' => $algo));

    $price = $price ? mbitcoinvaluetoa(take_yaamp_fee($price, $algo)) : '-';
    $norm = mbitcoinvaluetoa($norm);

    $t = time() - 24 * 60 * 60;

    $avgprice = controller()->memcache->get_database_scalar("current_avgprice-$algo",
        "select avg(price) from hashrate where algo=:algo and time>$t", array(':algo' => $algo));
    $avgprice = $avgprice ? mbitcoinvaluetoa(take_yaamp_fee($avgprice, $algo)) : '-';

    $total1 = controller()->memcache->get_database_scalar("current_total-$algo",
        "SELECT SUM(amount*price) AS total FROM blocks WHERE time>$t AND algo=:algo AND NOT category IN ('orphan','stake','generated')",
        array(':algo' => $algo)
    );

    $hashrate1 = controller()->memcache->get_database_scalar("current_hashrate1-$algo",
        "select avg(hashrate) from hashrate where time>$t and algo=:algo", array(':algo' => $algo));

    $algo_unit_factor = yaamp_algo_mBTC_factor($algo);
    $btcmhday1 = $hashrate1 != 0 ? mbitcoinvaluetoa($total1 / $hashrate1 * 1000000 * 1000 * $algo_unit_factor) : '';

//    $fees = yaamp_fee($algo);
    $port = getAlgoPort($algo);

    if ($defaultalgo == $algo)
        echo "<tr style='cursor: pointer; background-color: #b3cbff;' onclick='javascript:select_algo(\"$algo\")'>";
    else
        echo "<tr style='cursor: pointer' class='ssrow' onclick='javascript:select_algo(\"$algo\")'>";

    echo "<td><b>$algo</b></td>";
    echo "<td align=right style='font-size: .8em;'></td>";
    echo "<td align=right style='font-size: .8em;'><b>$coins</b></td>";
    echo "<td align=right style='font-size: .8em;'><b>$workers</b></td>";
    echo '<td align="right" style="font-size: .8em;" data="' . $hashrate . '"><b>' . $hashrate_sfx . '</b></td>';
    echo "<td align=right style='font-size: .8em;'></td>";

    if ($algo == $best_algo) {
        echo '<td class="estimate" align="right" style="font-size: .8em;" title="normalized ' . $norm . '"><b>' . $price . '*</b></td>';
    } else if ($norm > 0) {
        echo '<td class="estimate" align="right" style="font-size: .8em;" title="normalized ' . $norm . '">' . $price . '</td>';
    } else {
        echo '<td class="estimate" align="right" style="font-size: .8em;">' . $price . '</td>';
    }


    echo '<td class="estimate" align="right" style="font-size: .8em;">' . $avgprice . '</td>';

    if ($algo == $best_algo)
        echo '<td align="right" style="font-size: .8em;" data="' . $btcmhday1 . '"><b>' . $btcmhday1 . '*</b></td>';
    else
        echo '<td align="right" style="font-size: .8em;" data="' . $btcmhday1 . '">' . $btcmhday1 . '</td>';

    echo "</tr>";

    $fees = yaamp_fee($algo);
    $coinsList = getdbolist('db_coins', "algo=:algo and enable and visible", array(':algo' => $algo));

    drawCoinsList($coinsList, $fees);


    $total_coins += $coins;
    $total_miners += $workers;
}

echo "</tbody>";

if ($defaultalgo == 'all')
    echo "<tr style='cursor: pointer; background-color: #b3cbff;' onclick='javascript:select_algo(\"all\")'>";
else
    echo "<tr style='cursor: pointer' class='ssrow' onclick='javascript:select_algo(\"all\")'>";

echo "<td><b>all</b></td>";
echo "<td></td>";
echo "<td align=right style='font-size: .8em;'>$total_coins</td>";
echo "<td align=right style='font-size: .8em;'>$total_miners</td>";
echo "<td></td>";
echo "<td></td>";
echo '<td class="estimate"></td>';
echo '<td class="estimate"></td>';
echo "<td></td>";
echo "</tr>";

echo "</table>";

echo '<p style="font-size: .8em;">&nbsp;* values in mBTC/MH/day, per GH for sha & blake algos</p>';

echo "</div></div><br>";

function drawCoinsList($coinsList, $fees)
{

    foreach ($coinsList as $coin) {

//        $pool_hash_pow = yaamp_pool_rate_pow($itemCoin->algo);
//        $pool_hash_pow_sfx = $pool_hash_pow? Itoa2($pool_hash_pow).'h/s': '';

        $pool_hash = yaamp_coin_rate($coin->id);
        $pool_hash_sfx = $pool_hash ? Itoa2($pool_hash) . 'h/s' : '';

        $min_ttf = $coin->network_ttf > 0 ? min($coin->actual_ttf, $coin->network_ttf) : $coin->actual_ttf;
        $network_hash = $coin->difficulty * 0x100000000 / ($min_ttf ? $min_ttf : 60);
        $network_hash = $network_hash ? 'network hash ' . Itoa2($network_hash) . 'h/s' : '';

        //$stratum = getdbosql("db_stratums", "symbol=:symbol", array(':symbol' => $coin->symbol));
        $stratum = getStratumByCoin($coin);
        // echo "$itemCoin->symbol  $itemCoin->name  $stratumItem->port  " ;

        echo '<tr class="ssrow">';

        echo '<td><img width=16 src="' . $coin->image . '" style="margin-right: 7px;"><b><a href="/site/block?id=' . $coin->id . '">' . $coin->name . '</a></b></td>';
        if (isset($stratum)) {
            echo "<td align=right style='font-size: .8em;'>$stratum->port</td>";
        } else {
            echo "<td align=right style='font-size: .8em;'>none</td>";
        }
        echo '<td class="symb">' . $coin->symbol . '</td>';
        if (isset($stratum)) {
            echo "<td align=right style='font-size: .8em;'>$stratum->workers</td>";
        } else {
            echo "<td align=right style='font-size: .8em;'>-</td>";
        }

//        if($coin->auxpow && $coin->auto_ready)
//            echo "<td align=right style='font-size: .8em; opacity: 0.6;' title='merge mined\n$network_hash' data='$pool_hash_pow'>$pool_hash_pow_sfx</td>";
//        else {
        echo "<td align=right style='font-size: .8em;' title='$network_hash' data='$pool_hash'>$pool_hash_sfx</td>";
//}
        echo "<td align=right style='font-size: .8em;'>{$fees}%</td>";

        echo '</tr>';

    }

}

function getStratumByCoin($coin)
{

//    if (isset($coin)) {
//        return null;
//    }

    echo "coin symbol $coin->symbol<br/>";
    $stratum = getdbosql("db_stratums", "symbol=:symbol", array(':symbol' => $coin->symbol));
    if (!isset($stratum)) {
        echo "coin algo $coin->algo<br/>";
        $stratum = getdbosql("db_stratums", "algo=:algo", array(':algo' => $coin->algo));
    }
    return $stratum;

}


?>

<?php if (!$showestimates): ?>

    <style type="text/css">
        #maintable1 .estimate {
            display: none;
        }
    </style>

<?php endif; ?>
