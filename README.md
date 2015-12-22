###API Server Example written with symfony 2.0
AWS Codedeploy integration example for copying and modify permission
API interaction with different AWS Services: EC2/Autoscaling, DynamoDB
Main code could be found in AppBundle/Controller

####API functionality:
* /: welcome message
* /loadtest: generate random data (two char and one int) and insert into a relational DB (postgres in my case)
* /setup: modify an AWS Autoscaling group in order to prewarm the frontend fleet, start the database fleet
* /transfer: will move everything from the DB to an AWS DynamoDb table: this method implements transaction on the Postgres Side
* /count: return the number of AWS DynamoDB items
* /takedown: will shutdown the FE and DB fleet

