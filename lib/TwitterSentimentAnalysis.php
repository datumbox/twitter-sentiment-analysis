<?php
include_once(dirname(__FILE__).'/DatumboxAPI.php');
include_once(dirname(__FILE__).'/twitter-client.php');
class TwitterSentimentAnalysis {
    
    protected $datumbox_api_key; //Your Datumbox API Key. Get it from http://www.datumbox.com/apikeys/view/
    
    protected $consumer_key; //Your Twitter Consumer Key. Get it from https://dev.twitter.com/apps
    protected $consumer_secret; //Your Twitter Consumer Secret. Get it from https://dev.twitter.com/apps
    protected $access_key; //Your Twitter Access Key. Get it from https://dev.twitter.com/apps
    protected $access_secret; //Your Twitter Access Secret. Get it from https://dev.twitter.com/apps
    
    /**
    * The constructor of the class
    * 
    * @param string $datumbox_api_key   Your Datumbox API Key
    * @param string $consumer_key       Your Twitter Consumer Key
    * @param string $consumer_secret    Your Twitter Consumer Secret
    * @param string $access_key         Your Twitter Access Key
    * @param string $access_secret      Your Twitter Access Secret
    * 
    * @return TwitterSentimentAnalysis  
    */
    public function __construct($datumbox_api_key, $consumer_key, $consumer_secret, $access_key, $access_secret){
        $this->datumbox_api_key=$datumbox_api_key;
        
        $this->consumer_key=$consumer_key;
        $this->consumer_secret=$consumer_secret;
        $this->access_key=$access_key;
        $this->access_secret=$access_secret;
    }
    
    /**
    * This function fetches the twitter list and evaluates their sentiment
    * 
    * @param array $twitterSearchParams The Twitter Search Parameters that are passed to Twitter API. Read more here https://dev.twitter.com/docs/api/1.1/get/search/tweets
    * 
    * @return array
    */
    public function sentimentAnalysis($twitterSearchParams) {
        $tweets=$this->getTweets($twitterSearchParams);
        
        return $this->findSentiment($tweets);
    }
    
    /**
    * Calls the Search/tweets method of the Twitter API for particular Twitter Search Parameters and returns the list of tweets that match the search criteria.
    * 
    * @param mixed $twitterSearchParams The Twitter Search Parameters that are passed to Twitter API. Read more here https://dev.twitter.com/docs/api/1.1/get/search/tweets
    * 
    * @return array $tweets
    */
    protected function getTweets($twitterSearchParams) {
        $Client = new TwitterApiClient(); //Use the TwitterAPIClient
        $Client->set_oauth ($this->consumer_key, $this->consumer_secret, $this->access_key, $this->access_secret);

        $tweets = $Client->call('search/tweets', $twitterSearchParams, 'GET' ); //call the service and get the list of tweets
        
        unset($Client);
        
        return $tweets;
    }
    
    protected function findSentiment($tweets) {
        $DatumboxAPI = new DatumboxAPI($this->datumbox_api_key); //initialize the DatumboxAPI client
        
        $results=array();
        foreach($tweets['statuses'] as $tweet) { //foreach of the tweets that we received
            if(isset($tweet['metadata']['iso_language_code']) && $tweet['metadata']['iso_language_code']=='en') { //perform sentiment analysis only for the English Tweets
                $sentiment=$DatumboxAPI->TwitterSentimentAnalysis($tweet['text']); //call Datumbox service to get the sentiment
                
                if($sentiment!=false) { //if the sentiment is not false, the API call was successful.
                    $results[]=array( //add the tweet message in the results
                        'id'=>$tweet['id_str'],
                        'user'=>$tweet['user']['name'],
                        'text'=>$tweet['text'],
                        'url'=>'https://twitter.com/'.$tweet['user']['name'].'/status/'.$tweet['id_str'],
                        
                        'sentiment'=>$sentiment,
                    );
                }
            }
            
        }
        
        unset($tweets);
        unset($DatumboxAPI);
        
        return $results;
    }
}

  
