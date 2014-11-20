<?php
return array(

	"filter" => array(
		"italian_elision" => array(
			"type" => "elision",
			"articles" => array(
				"c", "l", "all", "dall", "dell",
				"nell", "sull", "coll", "pell",
				"gl", "agl", "dagl", "degl", "negl",
				"sugl", "un", "m", "t", "s", "v", "d"
			)
		),
		"italian_stop" => array(
		  "type"      =>  "stop",
		  "stopwords" =>  "_italian_" 
		),
		// "italian_keywords" => array(
		//   "type"     =>  "keyword_marker",
		//   "keywords" =>  [] 
		// ),
		"italian_stemmer" => array(
		  "type"     =>  "stemmer",
		  "language" =>  "italian" // "italian" | "light_italian"
		)
	),
	
	"analyzer" => array(
		"custom_lang_it" => array(
			"type" => "custom",
			"tokenizer" => "standard",
			"filter" => array(
				"asciifolding",
				"italian_elision",
				"lowercase",
				"italian_stop",
				//"italian_keywords",
				"italian_stemmer"
			)
		),
		"custom_html_lang_it" => array(
			"type" => "custom",
			"char_filter" => array("html_strip"),
			"tokenizer" => "standard",
			"filter" => array(
				"asciifolding",
				"italian_elision",
				"lowercase",
				"italian_stop",
				//"italian_keywords",
				"italian_stemmer"
			)
		)
	)

);