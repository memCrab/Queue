Config Libriary for php 8.0 core package
==========================
Install
--------
```composer require memcrab/queue```

require_once __DIR__ . "/needle_path/vendor/autoload.php";

use Memcrab\Queue;
use Memcrab\Queue\SQS;
use Memcrab\Queue\RMQ;


SQS Client
----------
For starting use SQS Client we need to create a connection to the server like this. Create an instance of SQS and use 2 methods - 
setConfigValue() and setConnect().

$connection = new SQS;
$connection->setConfigValue(string $region, string $key, string $secret, string $version, ?string $endpoint);
$connection->setConnect();


Method setConfigValue(string $region, string $key, string $secret, string $version, ?string $endpoint) - you must get region, credentials from your profile ($key and $secret), version,  
for example:

$connection->setConfigValue('us-west-2', 'your_aws_access_key_id', 'your_aws_secret_access_key', '2022-11-05');


Next we must declare a queue:

$connection->registerQueue('queue_name');


The method also have optional parameter $attibutes - array, where we can use:
- DelaySeconds (default 0),
- MaximumMessageSize (default 262.144 - 256KiB),
- MessageRetentionPeriod (default 345.600 - 4 days),
- Policy,
- ReceiveMessageWaitTimeSeconds (default 0),
- RedrivePolicy,
- VisibilityTimeout (default 30),
- KmsMasterKeyId,
- KmsDataKeyReusePeriodSeconds (default 300),
- SqsManagedSseEnabled,
- FifoQueue (default false),
- ContentBasedDeduplication (default false)

If we need to change message's visability, we can use:

$connection->changeMessageVisibility('queue_name', ['messages'], 10), where 10 - needle visibility timeout.


To send message:

$connection->sendMessage('queue_name', [message_body]);

We can add optional parameters:

- $attributes (array with BinaryListValues, BinaryValue, DataType, StringListValues and StringValue),
- $delaySeconds (default 10)

To receive message:

$connection->receiveMessage('queue_name');


To delete message fron queue:

$connection->deleteMessage('queue_name', ['message']);


To get queue's url:

$connection->getQueueUrl('queue_name');


If you need close connection, use:

$connection->shutdown();




RabbitMQ Client
---------------

For starting use RabbitMQ Client we need to create a connection to the server like this. Create an instance of RMQ and use 2 methods - 
setConfigValue() and setConnect().

$connection = new RMQ;
$connection->setConfigValue(string $host, string $port, string $username, string $pass);
$connection->setConnect();


Method setConfigValue(string $host, string $port, string $username, string $pass) - you must get hostname, port, username and password, 
for example:

$connection->setConfigValue('localhost', '5672', 'guest', 'guest');

Next the library will create a channel and we must declare a queue:

$connection->registerQueue('queue_name');


The method also have optional parameters like:
- $passive (true or false, default false),
- $durable (true or false, default false),
- $exclusive (true or false, default false),
- $auto_delete (true or false, default false)
You can use these parameters like:

$connection->registerQueue('queue_name', false, true, false, false);


Also we can create an exchange like:

$connection->registerExchange('exchange_name', 'direct');


The method also have optional parameters like:
- $type ('direct', 'topic', 'fanout', 'header'),
- $passive (true or false, default false),
- $durable (true or false, default false),
- $auto_delete (true or false, default false)
You can use these parameters like:

$connection->registerExchange('exchange_name', 'direct', true, true, false);


To send message:

$connection->sendMessage('queue_name', [message_body]);


You can create third parameter - 'exchange_name', if you need it.

To receive message:

$connection->receiveMessage('queue_name');


The method also have optional parameters like:
- $consumer_tag (default ''),
- $no_local (true or false, default false),
- $no_ack (true or false, default false),
- $exclusive (true or false, default false),
- $nowait (true or false, default false),
- $callback (default null, there could be an anonymous function or the name of an existing function)
You can use these parameters like:

$connection->receiveMessage('queue_name', 'consumer_tag', true, false, true, 'my_function');


If you need to tell the exchange to send messages fron the queue, we can create the relationship between them like this:

$connection->queueBind('queue_name', 'exchange_name', 'routing_key');


If you need close channel and connection, use:

$connection->shutdown();