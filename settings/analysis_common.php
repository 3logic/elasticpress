<?php
return array(

	"filter" => array(
		"hunspell_it" => array(
			"type"  => "hunspell",
			"locale" =>  "it_IT",
			"dedup" =>  true
		)
	),
	"char_filter" => array(
		"silly_filter" => array(
			"type" => "mapping",
			"mappings" => ["ch=>k"]
		)
	)
);