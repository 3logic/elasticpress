<?php
namespace Elasticpress\Job;

use TFramework\JobManager\Shell\OperationJob as OperationJob;
use Elasticpress\EPClient;

class ReindexJob extends OperationJob{

	public function act(){
		$logger = $this->_generate_logger();
		$parameters = $this->get_parameters();
		$index_version = $parameters['index_version'];

		try{
			$epclient = EPClient::get_instance();
			if($index_version)
				$epclient->elect_index($index_version);
			$result = $epclient->reindex('\Elasticpress\EPMapper');
			$logger->log($result);
		}
		catch(\Exception $e){
			$result = null;
			$logger->error($e->getMessage());
		}
		
		try{
			$logger->close();
		}catch(\Exception $e){
			//dont care
		}

		return $result;
	}

}