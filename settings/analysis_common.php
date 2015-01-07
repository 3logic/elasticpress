<?php
return array(
	
	"filter" => array(
		"edge_ngram" => array(
			"side" => "front",
			"max_gram" => 20,
			"min_gram" => 3,
			"type" => "edgeNGram"
		)
	// 	"hunspell_it" => array(
	// 		"type"  => "hunspell",
	// 		"locale" =>  "it_IT",
	// 		"dedup" =>  true
	// 	)
	// ),
	// "char_filter" => array(
	// 	"silly_filter" => array(
	// 		"type" => "mapping",
	// 		"mappings" => ["che=>ke","chi=>ki","per=>x"]
	// 	)
	),
	
	"analyzer" => array(
		"custom_folded" => array(
			"type" => "custom",
			"tokenizer" => "standard",
			"filter" => array(
				"asciifolding",
				"lowercase"
			)
		),
		"custom_autocomplete" => array(
			"type" => "custom",
			"tokenizer" => "standard",
			"filter" => array(
				"asciifolding",
				"lowercase",
				"edge_ngram"
			)
		)
	)
);