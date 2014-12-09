<?php
namespace Elasticpress\Job;

use TFramework\JobManager\Shell\OperationJob as OperationJob;
use Elasticpress\EPPostStatusAccessor;

class PostIndexerJob extends OperationJob{

	public function act(){
		$logger = $this->_generate_logger();
		
		try{
			$result = EPPostStatusAccessor::index_pending_posts();
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