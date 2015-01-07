<?php
return array(

    "filter" => array(
        "english_stop" => array(
          "type"      =>  "stop",
          "stopwords" =>  "_english_" 
        ),
        // "english_keywords" => array(
        //   "type"     =>  "keyword_marker",
        //   "keywords" =>  [] 
        // ),
        "english_stemmer" => array(
          "type"     =>  "stemmer",
          "language" =>  "english"
        ),
        "english_possessive_stemmer" => array(
          "type"     =>  "stemmer",
          "language" =>  "possessive_english"
        )
    ),
    
    "analyzer" => array(
        "custom_lang_en" => array(
            "type" => "custom",
            "tokenizer" => "standard",
            "filter" => array(
                "english_possessive_stemmer",
                "lowercase",
                "english_stop",
                //"english_keywords",
                "english_stemmer"
            )
        ),
        "custom_html_lang_en" => array(
            "type" => "custom",
            "char_filter" => array("html_strip"),
            "tokenizer" => "standard",
            "filter" => array(
                "english_possessive_stemmer",
                "lowercase",
                "english_stop",
                //"english_keywords",
                "english_stemmer"
            )
        )
    )

);