<?php
namespace Elasticpress\Job;

use TFramework\JobManager\Shell\OperationJob as OperationJob;
use Elasticpress\EPPlugin,
	Elasticpress\EPClient;

class ReindexJob extends OperationJob{

	public function act(){
		$logger = $this->_generate_logger();
		$parameters = $this->get_parameters();

		$blog_id = $parameters['blog_id'];
		switch_to_blog($blog_id);

		$from_index = $parameters['from_index'];
		$result = null;

		try{
			EPPlugin::init();
			$epclient = EPClient::get_instance(null,true);
			if($from_index)
				$epclient->elect_index($from_index);
			$new_index = $epclient->reindex('\Elasticpress\EPMapper');
			$result = True;
		}
		catch(\Exception $e){
			$logger->error($e->getMessage());
		}
		
		try{
			$logger->close();
		}catch(\Exception $e){
			//dont care
		}

		restore_current_blog();
		return $result;
	}

}