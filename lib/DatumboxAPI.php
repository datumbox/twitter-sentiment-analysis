<?php
/**
 * Example of API Client for Datumbox Machine Learning API.
 * 
 * @author Vasilis Vryniotis
 * @link   http://www.datumbox.com/
 * @copyright Copyright (c) 2013, Datumbox.com
 */
class DatumboxAPI {
    const version='1.0';
    
    protected $api_key;
    
    /**
    * Constructor
    * 
    * @param string $api_key
    * @return DatumboxAPI
    */
    public function __construct($api_key) {
        $this->api_key=$api_key;
    }
    
    /**
    * Calls the Web Service of Datumbox
    * 
    * @param string $api_method
    * @param array $POSTparameters
    * 
    * @return string $jsonreply
    */
    protected function CallWebService($api_method,$POSTparameters) {
        $POSTparameters['api_key']=$this->api_key;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://api.datumbox.com/'.self::version.'/'.$api_method.'.json');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_ENCODING, 'gzip,deflate');
        curl_setopt($ch, CURLOPT_POST, true );
        curl_setopt($ch, CURLOPT_POSTFIELDS, $POSTparameters);

        $jsonreply = curl_exec ($ch);
        curl_close ($ch);
        unset($ch);

        return $jsonreply;
    }
    
    /**
    * Parses the API Reply
    * 
    * @param mixed $jsonreply
    * 
    * @return mixed
    */
    protected function ParseReply($jsonreply) {
        $jsonreply=json_decode($jsonreply,true);
        
        if(isset($jsonreply['output']['status']) && $jsonreply['output']['status']==1) {
            return $jsonreply['output']['result'];
        }
        
        if(isset($jsonreply['error']['ErrorCode']) && isset($jsonreply['error']['ErrorMessage'])) {
            echo $jsonreply['error']['ErrorMessage'].' (ErrorCode: '.$jsonreply['error']['ErrorCode'].')';
        }
        
        return false;
    }
    
    /**
    * Performs Sentiment Analysis.
    * 
    * @param string $text The clear text (no HTML tags) that we evaluate.
    * 
    * @return string|false It returns "positive", "negative" or "neutral" on success and false on fail.
    */
    public function SentimentAnalysis($text) {
        $parameters=array(
            'text'=>$text,
        );
        
        $jsonreply=$this->CallWebService('SentimentAnalysis',$parameters);
        
        return $this->ParseReply($jsonreply);
    }
    
    /**
    * Performs Sentiment Analysis on Twitter.
    * 
    * @param string $text The text of the tweet that we evaluate.
    * 
    * @return string|false It returns "positive", "negative" or "neutral" on success and false on fail.
    */
    public function TwitterSentimentAnalysis($text) {
        $parameters=array(
            'text'=>$text,
        );
        
        $jsonreply=$this->CallWebService('TwitterSentimentAnalysis',$parameters);
        
        return $this->ParseReply($jsonreply);
    }
    
    /**
    * Performs Subjectivity Analysis.
    * 
    * @param string $text The clear text (no HTML tags) that we evaluate.
    * 
    * @return string|false. It returns "objective" or "subjective" on success and false on fail.
    */
    public function SubjectivityAnalysis($text) {
        $parameters=array(
            'text'=>$text,
        );
        
        $jsonreply=$this->CallWebService('SubjectivityAnalysis',$parameters);
        
        return $this->ParseReply($jsonreply);
    }
    
    /**
    * Performs Topic Classification. 
    * 
    * @param string $text The clear text (no HTML tags) that we evaluate.
    * 
    * @return string|false. It returns "Arts", "Business & Economy", "Computers & Technology", "Health", "Home & Domestic Life", "News", "Recreation & Activities", "Reference & Education", "Science", "Shopping", "Society", "Sports" on success and false on fail.
    */
    public function TopicClassification($text) {
        $parameters=array(
            'text'=>$text,
        );
        
        $jsonreply=$this->CallWebService('TopicClassification',$parameters);
        
        return $this->ParseReply($jsonreply);
    }
    
    /**
    * Performs Spam Detection.
    * 
    * @param string $text The clear text (no HTML tags) that we evaluate.
    * 
    * @return string|false It returns "spam" or "nospam" on success and false on fail.
    */
    public function SpamDetection($text) {
        $parameters=array(
            'text'=>$text,
        );
        
        $jsonreply=$this->CallWebService('SpamDetection',$parameters);
        
        return $this->ParseReply($jsonreply);
    }
    
    /**
    * Performs Adult Content Detection. 
    * 
    * @param string $text The clear text (no HTML tags) that we evaluate.
    * 
    * @return string|false It returns "adult" or "noadult" on success and false on fail.
    */
    public function AdultContentDetection($text) {
        $parameters=array(
            'text'=>$text,
        );
        
        $jsonreply=$this->CallWebService('AdultContentDetection',$parameters);
        
        return $this->ParseReply($jsonreply);
    }
    
    /**
    * Performs Readability Assessment. 
    * 
    * @param string $text The clear text (no HTML tags) that we evaluate.
    * 
    * @return string|false It returns "basic", "intermediate" or "advanced" on success and false on fail.
    */
    public function ReadabilityAssessment($text) {
        $parameters=array(
            'text'=>$text,
        );
        
        $jsonreply=$this->CallWebService('ReadabilityAssessment',$parameters);
        
        return $this->ParseReply($jsonreply);
    }
    
    /**
    * Performs Language Detection. 
    * 
    * @param string $text The clear text (no HTML tags) that we evaluate.
    * 
    * @return string|false It returns the ISO639-1 two-letter language code (http://en.wikipedia.org/wiki/List_of_ISO_639-1_codes) on success and false on fail.
    */
    public function LanguageDetection($text) {
        $parameters=array(
            'text'=>$text,
        );
        
        $jsonreply=$this->CallWebService('LanguageDetection',$parameters);
        
        return $this->ParseReply($jsonreply);
    }
    
    /**
    * Performs Commercial Detection. 
    * 
    * @param string $text The clear text (no HTML tags) that we evaluate.
    * 
    * @return string|false It returns "commercial" or "noncommercial" on success and false on fail.
    */
    public function CommercialDetection($text) {
        $parameters=array(
            'text'=>$text,
        );
        
        $jsonreply=$this->CallWebService('CommercialDetection',$parameters);
        
        return $this->ParseReply($jsonreply);
    }
    
    /**
    * Performs Educational Detection. 
    * 
    * @param string $text The clear text (no HTML tags) that we evaluate.
    * 
    * @return string|false It returns "educational" or "noneducational" on success and false on fail.
    */
    public function EducationalDetection($text) {
        $parameters=array(
            'text'=>$text,
        );
        
        $jsonreply=$this->CallWebService('EducationalDetection',$parameters);
        
        return $this->ParseReply($jsonreply);
    }
    
    /**
    * Performs Gender Detection. 
    * 
    * @param string $text The clear text (no HTML tags) that we evaluate.
    * 
    * @return string|false It returns "male" or "female" on success and false on fail.
    */
    public function GenderDetection($text) {
        $parameters=array(
            'text'=>$text,
        );
        
        $jsonreply=$this->CallWebService('GenderDetection',$parameters);
        
        return $this->ParseReply($jsonreply);
    }
    
    /**
    * Performs Text Extraction. It extracts the important information (clear text) from a given webpage.
    * 
    * @param string $text The HTML of the webpage.
    * 
    * @return string|false It returns the clear text of the document on success and false on fail.
    */
    public function TextExtraction($text) {
        $parameters=array(
            'text'=>$text,
        );
        
        $jsonreply=$this->CallWebService('TextExtraction',$parameters);
        
        return $this->ParseReply($jsonreply);
    }
    
    /**
    * Performs Keyword Extraction. It extracts the keywords and keywords combinations from a text.
    * 
    * @param string $text The clear text (no HTML tags) that we analyze.
    * @param integer $n It is a number from 1 to 5 which denotes the number of Keyword combinations that we want to get.
    * 
    * @return array|false It returns an array with the keywords of the document on success and false on fail.
    */
    public function KeywordExtraction($text,$n) {
        $parameters=array(
            'text'=>$text,
            'n'=>$n,
        );
        
        $jsonreply=$this->CallWebService('KeywordExtraction',$parameters);
        
        return $this->ParseReply($jsonreply);
    }
    
    /**
    * Evaluates the Document Similarity between 2 documents.
    * 
    * @param string $original The first clear text (no HTML tags) that we compare.
    * @param string $copy The second clear text (no HTML tags) that we compare.
    * 
    * @return array|false It returns an array with similarity metrics for the two documents on success and false on fail.
    */
    public function DocumentSimilarity($original,$copy) {
        $parameters=array(
            'original'=>$original,
            'copy'=>$copy,
        );
        
        $jsonreply=$this->CallWebService('DocumentSimilarity',$parameters);
        
        return $this->ParseReply($jsonreply);
    }
    
    
}

