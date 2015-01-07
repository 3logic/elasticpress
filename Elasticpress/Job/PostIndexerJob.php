<?php
namespace Elasticpress\Job;

use TFramework\JobManager\Shell\OperationJob as OperationJob;
use Elasticpress\EPPostStatusAccessor,
	Elasticpress\EPClient,
	Elasticpress\EPPlugin;

class PostIndexerJob extends OperationJob{

	public function act(){
		$logger = $this->_generate_logger();
		$parameters = $this->get_parameters();
		
		$blog_id = $parameters['blog_id'];
		switch_to_blog($blog_id);

		$result = null;

		try{
			EPPlugin::init();
			$indexed = EPPostStatusAccessor::index_pending_posts();
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