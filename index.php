<!DOCTYPE html>
<html>
<head>
<meta http-equiv="Cache-Control" content="no-cache">
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<meta http-equiv="Lang" content="en">
<title>Datumbox Twitter Sentiment Analysis Demo</title>
</head>
<body>
<h1>Datumbox Twitter Sentiment Analysis</h1>
<p>Type your keyword below to perform Sentiment Analysis on Twitter Results:</p>
<form method="GET">
    <label>Keyword: </label> <input type="text" name="q" /> 
    <input type="submit" />
</form>

<?php

if(isset($_GET['q']) && $_GET['q']!='') {
    include_once(dirname(__FILE__).'/config.php');
    include_once(dirname(__FILE__).'/lib/TwitterSentimentAnalysis.php');

    $TwitterSentimentAnalysis = new TwitterSentimentAnalysis(DATUMBOX_API_KEY,TWITTER_CONSUMER_KEY,TWITTER_CONSUMER_SECRET,TWITTER_ACCESS_KEY,TWITTER_ACCESS_SECRET);

    //Search Tweets parameters as described at https://dev.twitter.com/docs/api/1.1/get/search/tweets
    $twitterSearchParams=array(
        'q'=>$_GET['q'],
        'lang'=>'en',
        'count'=>10,
    );
    $results=$TwitterSentimentAnalysis->sentimentAnalysis($twitterSearchParams);


    ?>
    <h1>Results for "<?php echo $_GET['q']; ?>"</h1>
    <table border="1">
        <tr>
            <td>Id</td>
            <td>User</td>
            <td>Text</td>
            <td>Twitter Link</td>
            <td>Sentiment</td>
        </tr>
        <?php
        foreach($results as $tweet) {
            
            $color=NULL;
            if($tweet['sentiment']=='positive') {
                $color='#00FF00';
            }
            else if($tweet['sentiment']=='negative') {
                $color='#FF0000';
            }
            else if($tweet['sentiment']=='neutral') {
                $color='#FFFFFF';
            }
            ?>
            <tr style="background:<?php echo $color; ?>;">
                <td><?php echo $tweet['id']; ?></td>
                <td><?php echo $tweet['user']; ?></td>
                <td><?php echo $tweet['text']; ?></td>
                <td><a href="<?php echo $tweet['url']; ?>" target="_blank">View</a></td>
                <td><?php echo $tweet['sentiment']; ?></td>
            </tr>
            <?php
        }
        ?>    
    </table>
    <?php
}

?>
  
</body>
</html>
