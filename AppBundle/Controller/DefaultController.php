<?php

namespace AppBundle\Controller;


use Aws\AutoScaling\AutoScalingClient;
use Aws\DynamoDb\DynamoDbClient;
use Aws\Ec2\Ec2Client;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;


class DefaultController extends Controller
{
    /**
     * @Route("/", name="homepage")
     */
   public function home() {
	return new Response(
	   '<html><body>Welcome tradetracker engineer!</body></html>'
	);
   }
   /**
    * @Route("/test")
    */

   public function loadtestAct()
       {
	    $db_connection = pg_connect("host=".$this->getParameter('database_host')." dbname=".$this->getParameter('database_name')." user=".$this->getParameter('database_user')." password=".$this->getParameter('database_password')."");
	    if (!$db_connection) {
 		   print("Connection Failed.");
	    } 
	   $age = rand(1, 80);
           $name_length = rand(5,10);
	   $surname_length = rand(4,12);
           $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
	   $name= substr(str_shuffle($characters), 0, $name_length);
	   $surname= substr(str_shuffle($characters), 0, $surname_length);
           $query = "INSERT INTO users (name, surname, age) VALUES ($1, $2, $3)";
	   pg_prepare('insert',  $query);
	   pg_execute('insert', array($name, $surname, $age)) || die("Error inserting data"); 
	   pg_close($db_connection);
	return new Response(
               '<html><body>TEST: OK</body></html>'
               //'<html><body>OK<br></body></html>'
           );
       }
   /**
    * @Route("/setup")
    */
   public function setupAct()
       {
	//Starting autoscaling FE fleet
	 $as_client = AutoScalingClient::factory(array(
            'region'  => 'us-east-1',
            'version' => 'latest'
                ));
         $as_result = $as_client->updateAutoScalingGroup(array(
            'AutoScalingGroupName' => ''.$this->getParameter('as_fleet').'',
            'MinSize' => 3,
            'MaxSize' => 10,
            'DesiredCapacity' => 3
            ));
         if ($as_result) {
		echo "Setup: starting autoscaling fleet <br />";
	 }  
	 
	 //Starting db fleet if stopped
	  $ec2_client = Ec2Client::factory(array(
                'region'   => 'us-east-1',
                'version'  => 'latest'
                ));
          $ec2_result = $ec2_client->describeInstances(array(
                'Filters' => array(array(
                        'Name' => 'tag:Name',
                        'Values' => array(''.$this->getParameter('db_tag').'')
                       )
                )
        ));
        $reservations = $ec2_result['Reservations'];
        foreach ($reservations as $reservation) {
                $instances = $reservation['Instances'];
                        foreach ($instances as $instance) {
                                if ($instance['State']['Name'] == "stopped"){
                                        echo "Setup: Starting DB instance....<br />";
                                        $start = $ec2_client->startInstances(array(
                                                'InstanceIds' => array(''.$instance['InstanceId'].'')
                                        ));
                                } else {echo "DB Instance is running....<br />";}      
                        }
        }

        return new Response(
        //          '<html><body>Setup initiated, starting frontend fleet</body></html>'
         );
       }
   /**
    * @Route("/transfer")
    */
   public function transferAct()
       {
            $db_connection = pg_connect("host=".$this->getParameter('database_host')." dbname=".$this->getParameter('database_name')." user=".$this->getParameter('database_user')." password=".$this->getParameter('database_password')."");
	    $ddb_client = DynamoDbClient::factory(array(
            'region'  => 'us-east-1',
            'version' => 'latest'
                ));
            if (!$db_connection) {
                   print("Connection Failed.");
            } 
            pg_query("BEGIN");
	    $insert_res = pg_query($db_connection, "SELECT * FROM users");
	    $count = pg_num_rows($insert_res);
	    if ($count > 0) {
	 	while ($row = pg_fetch_row($insert_res)) {
			  $result = $ddb_client->putItem(array(
				'TableName' => ''.$this->getParameter('ddb_table').'',
				'Item' => array(
					'name'		=> array('S' => ''.$row[0].''),	
					'surname'	=> array('S' => ''.$row[1].''),	
					'age'		=> array('N' => ''.$row[2].'')
					)
				));
			} 
		if ($result){ 
			$delete_db = pg_query($db_connection, "DELETE FROM users") || die("Problem deleting users");
			pg_query("COMMIT");
		} else {
			pg_query("ROLLBACK");
		}
	  } else {
		echo "Record on database: $count";
	  }
	  pg_close($db_connection);
           return new Response(
               '<html><body>TRANSFER Completed</body></html>'
           );
       }
   /**
    * @Route("/count")
    */
   public function countAct()
       {
	   $ddb_client = DynamoDbClient::factory(array(
            'region'  => 'us-east-1',
            'version' => 'latest'
                ));
	   $result = $ddb_client->scan(array(
    		'TableName' => ''.$this->getParameter('ddb_table').'',
        	'Count'     => true
	   ));
           return new Response(
               '<html><body>COUNT: '.$result['Count'].'</body></html>'
           );
       }
   /**
    * @Route("/teardown")
    */
   public function teardownAct()
       {
           return new Response(
               '<html><body>TEARDOWN</body></html>'
           );
       }
   /**
    * @Route("/takedown")
    */
   public function takedownAct()
       {
	 //stopping FE fleet
         $as_client = AutoScalingClient::factory(array(
            'region'  => 'us-east-1',
            'version' => 'latest'
                ));
         $as_result = $as_client->updateAutoScalingGroup(array(
            'AutoScalingGroupName' => ''.$this->getParameter('as_fleet').'',
            'MinSize' => 0,
            'MaxSize' => 0,
            'DesiredCapacity' => 0
            ));
         
         if ($as_result) {
          	echo "TakeDown: taking down frontend fleet <br />";     
          }

	  //stopping db tier
           $ec2_client = Ec2Client::factory(array(
		'region'   => 'us-east-1',
		'version'  => 'latest'
		));
 	   $result = $ec2_client->describeInstances(array(
    		'Filters' => array(array(
            		'Name' => 'tag:Name',
            		'Values' => array(''.$this->getParameter('db_tag').'')
 		       )
    		)
	));
        $reservations = $result['Reservations'];
	foreach ($reservations as $reservation) {
		$instances = $reservation['Instances'];
			foreach ($instances as $instance) {
				if ($instance['State']['Name'] == "running"){
					echo "Stopping DB instance...<br />";
					$stop = $ec2_client->stopInstances(array(
						'InstanceIds' => array(''.$instance['InstanceId'].'')
					));
				}	
			}
	}
           return new Response(
        //       '<html><body><br />TAKEDOWN:</body></html>'
           );
       }
}
