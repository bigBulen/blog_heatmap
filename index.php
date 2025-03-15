<!--博客热力图 start-->
 
<?php 
date_default_timezone_set('Asia/Shanghai');
 
// 获取RSS内容（使用cURL）
function getRssContent($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $content = curl_exec($ch);
    
    if (curl_errno($ch)) {
        die("无法获取RSS内容: " . curl_error($ch));
    }
    
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($httpCode !== 200) {
        die("RSS请求失败，HTTP状态码: $httpCode");
    }
    
    curl_close($ch);
    return $content;
}
 
// 解析RSS并统计字数
function parseRss($rssContent) {
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($rssContent);
    
    if ($xml === false) {
        die("RSS解析失败");
    }
    
    $stats = [];
    $namespaces = $xml->getNamespaces(true);
    
    foreach ($xml->channel->item as $item) {
        $pubDate = (string)$item->pubDate;
        $date = date('Y-m-d', strtotime($pubDate));
        
        $contentNS = $item->children($namespaces['content']);
        $contentEncoded = (string)$contentNS->encoded;
        
        $content = html_entity_decode(strip_tags($contentEncoded));
        $content = preg_replace('/\s+/', ' ', $content);
        $content = trim($content);
        
        $wordCount = mb_strlen($content);
        
        if (!isset($stats[$date])) {
            $stats[$date] = 0;
        }
        $stats[$date] += $wordCount;
    }
    
    return $stats;
}
 
// 生成完整日期范围
function generateDateRange($startDate, $endDate) {
    $interval = new DateInterval('P1D');
    $period = new DatePeriod($startDate, $interval, $endDate);
    
    $fullStats = [];
    foreach ($period as $date) {
        $dateStr = $date->format('Y-m-d');
        $fullStats[$dateStr] = 0;
    }
    
    return $fullStats;
}
 
// 颜色等级计算
function getColorLevel($count) {
    if ($count === 0) return 0;
    elseif ($count <= 100) return 1;
    elseif ($count <= 300) return 2;
    elseif ($count <= 500) return 3;
    else return 4;
}
 
// 主程序
try {
    $rssUrl = 'https://123.60.42.216/feed'; // 请在此处填写你的 RSS 地址
    $rssContent = getRssContent($rssUrl);
    $stats = parseRss($rssContent);
    
    // 调整日期范围，使其从最近一年前的周一开始，到最近的周日结束
    $startDate = new DateTime('-1 year');
    if ($startDate->format('N') != 1) {  // N: 1 (周一) ... 7 (周日)
        $startDate->modify('last monday');
    }
    $endDate = new DateTime();
    if ($endDate->format('N') != 7) {
        $endDate->modify('next sunday');
    }
    $endDate->modify('+1 day'); // 包含最后一天
    
    $fullStats = generateDateRange($startDate, $endDate);
    
    // 合并统计数据
    foreach ($stats as $date => $count) {
        if (isset($fullStats[$date])) {
            $fullStats[$date] = $count;
        }
    }
    
    // 计算总字数
    $totalCount = array_sum($fullStats);
    
    // 将统计数据按天顺序存入数组，并按每7天一组分成每周的数据
    $days = [];
    foreach ($fullStats as $date => $count) {
        $days[] = [
            'date' => $date,
            'count' => $count,
            'level' => getColorLevel($count)
        ];
    }
    $weeks = array_chunk($days, 7);
    
    // 生成每列（月）的标签：当本周第一天的月份与上一周不同则显示
    $monthLabels = [];
    $prevMonth = '';
    foreach ($weeks as $i => $week) {
        $weekStart = new DateTime($week[0]['date']);
        $month = $weekStart->format('n'); // 月份（无前导零）
        $year = $weekStart->format('Y');
        $label = "$year.$month";
        if ($month !== $prevMonth) {
            $monthLabels[$i] = $label;
            $prevMonth = $month;
        } else {
            $monthLabels[$i] = '';
        }
    }
    
} catch (Exception $e) {
    die("发生错误: " . $e->getMessage());
}
?>
 
<!--主HTML-->
<style>
    .heatmap-table {
        border-collapse: collapse;
        margin: 0 auto;
    }
    .heatmap-table th, .heatmap-table td {
        padding: 2px;
    }
    .month-label {
        text-align: center;
        font-size: 12px;
        color: #666;
    }
    .day-label {
        font-size: 12px;
        color: #666;
        text-align: right;
        padding-right: 4px;
    }
    .day-cell {
        width: 12px;
        height: 12px;
        background-color: #ebedf0;
        border-radius: 2px;
        position: relative;
    }
    .day-cell:hover::after {
        content: attr(data-date) ": " attr(data-count) "字";
        position: absolute;
        top: -30px;
        left: 50%;
        transform: translateX(-50%);
        background: #333;
        color: #fff;
        padding: 4px 8px;
        border-radius: 4px;
        white-space: nowrap;
        font-size: 12px;
        z-index: 10;
    }
    .level-0 { background-color: #ebedf0; }
    .level-1 { background-color: #c6e48b; }
    .level-2 { background-color: #7bc96f; }
    .level-3 { background-color: #239a3b; }
    .level-4 { background-color: #196127; }
    
    .scroll-container {
      overflow-x: auto;            /* 允许水平滚动 */
      -webkit-overflow-scrolling: touch; /* iOS上更顺滑的滚动 */
    }
</style>
<div class="scroll-container">
<!-- 上部月份标签 -->
<table class="heatmap-table">
    <tr>
        <th></th>
        <?php foreach ($weeks as $index => $week): ?>
            <th class="month-label"><?= $monthLabels[$index] ?></th>
        <?php endforeach; ?>
    </tr>
</table>
 
<!-- 主体热力图 -->
<table class="heatmap-table">
    <?php 
        // 定义一周内7天的名称（从周一到周日）
        $dayNames = ["周一", "周二", "周三", "周四", "周五", "周六", "周日"];
        // 需要显示标签的行索引：0（周一）、2（周三）、4（周五）、6（周日）
        $labelRows = [0, 2, 4, 6];
    ?>
    <?php for ($i = 0; $i < 7; $i++): ?>
        <tr>
            <td class="day-label">
                <?php if (in_array($i, $labelRows)): ?>
                    <?= $dayNames[$i] ?>
                <?php endif; ?>
            </td>
            <?php foreach ($weeks as $week): ?>
                <?php $day = $week[$i]; ?>
                <td>
                    <div class="day-cell level-<?= $day['level'] ?>" 
                         data-date="<?= $day['date'] ?>" 
                         data-count="<?= $day['count'] ?>">
                    </div>
                </td>
            <?php endforeach; ?>
        </tr>
    <?php endfor; ?>
</table>
 
<!-- 底部统计总字数 -->
<div style="text-align:center; margin-top:20px; font-size:14px; color:#333;">
    本站近365天的废话总产量(含代码、「说说」短文章)：<?= $totalCount ?>
</div>
</div>
<!--博客热力图 end-->
