<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Action;

use byteShard\Database;
use byteShard\Debug;
use byteShard\Exception;
use byteShard\Internal\Action;
use byteShard\Internal\Action\ActionResultInterface;
use byteShard\Queueable;
use DateTime;

class AddToQueue extends Action
{
    private string $className;
    private string $data;
    private string $queueId;

    /**
     * AddToQueue constructor.
     * @param Queueable $queueable
     * @param string $queueId
     */
    public function __construct(Queueable $queueable, string $queueId = 'default')
    {
        $this->className = get_class($queueable);
        $this->data      = serialize($queueable->getData());
        if (preg_match('/^[a-z]{2,10}$/', $queueId) !== 1) {
            $queueId = 'default';
        }
        $this->queueId = $queueId;
    }

    /**
     * @throws Exception
     */
    protected function runAction(): ActionResultInterface
    {
        // TODO: this action doesn't work anymore. Maybe useless and rather use a real queue
        $parameters = ['class' => $this->className, 'data' => $this->data, 'queue' => $this->queueId, 'tries' => 1, 'createdOn' => (new DateTime())->format('Y-m-d H:i:s')];
        Database::insert('INSERT INTO bs_queue (class, data, queue, tries, createdOn) VALUES (:class, :data, :queue, :tries, :createdOn)', $parameters);

        $output     = [];
        $return_var = 0;
        $phpBin     = exec('which php', $output, $return_var);
        if ($return_var === 0) {
            $command = $phpBin.' '.BS_FILE_PUBLIC_ROOT.DIRECTORY_SEPARATOR.'bs'.DIRECTORY_SEPARATOR.'bs_queue.php '.$this->queueId.' > /dev/null &';
            Debug::info('PHP CLI: '.$command);
            exec($command);
        }
        return new Action\ActionResult();
    }
}
