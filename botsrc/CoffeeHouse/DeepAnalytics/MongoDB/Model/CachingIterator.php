<?php

    namespace MongoDB\Model;

    use Countable;
    use Iterator;
    use IteratorIterator;
    use Traversable;
    use function count;
    use function current;
    use function key;
    use function next;
    use function reset;

    /**
     * Iterator for wrapping a Traversable and caching its results.
     *
     * By caching results, this iterators allows a Traversable to be counted and
     * rewound multiple times, even if the wrapped object does not natively support
     * those operations (e.g. MongoDB\Driver\Cursor).
     *
     * @internal
     */
    class CachingIterator implements Countable, Iterator
    {
        /** @var array */
        private $items = [];

        /** @var IteratorIterator */
        private $iterator;

        /** @var boolean */
        private $iteratorAdvanced = false;

        /** @var boolean */
        private $iteratorExhausted = false;

        /**
         * Initialize the iterator and stores the first item in the cache. This
         * effectively rewinds the Traversable and the wrapping Generator, which
         * will execute up to its first yield statement. Additionally, this mimics
         * behavior of the SPL iterators and allows users to omit an explicit call
         * to rewind() before using the other methods.
         *
         * @param Traversable $traversable
         */
        public function __construct(Traversable $traversable)
        {
            $this->iterator = new IteratorIterator($traversable);

            $this->iterator->rewind();
            $this->storeCurrentItem();
        }

        /**
         * @see http://php.net/countable.count
         * @return integer
         */
        public function count()
        {
            $this->exhaustIterator();

            return count($this->items);
        }

        /**
         * @see http://php.net/iterator.current
         * @return mixed
         */
        public function current()
        {
            return current($this->items);
        }

        /**
         * @see http://php.net/iterator.key
         * @return mixed
         */
        public function key()
        {
            return key($this->items);
        }

        /**
         * @see http://php.net/iterator.next
         * @return void
         */
        public function next()
        {
            if (! $this->iteratorExhausted) {
                $this->iteratorAdvanced = true;
                $this->iterator->next();

                $this->storeCurrentItem();

                $this->iteratorExhausted = ! $this->iterator->valid();
            }

            next($this->items);
        }

        /**
         * @see http://php.net/iterator.rewind
         * @return void
         */
        public function rewind()
        {
            /* If the iterator has advanced, exhaust it now so that future iteration
             * can rely on the cache.
             */
            if ($this->iteratorAdvanced) {
                $this->exhaustIterator();
            }

            reset($this->items);
        }

        /**
         * @see http://php.net/iterator.valid
         * @return boolean
         */
        public function valid()
        {
            return $this->key() !== null;
        }

        /**
         * Ensures that the inner iterator is fully consumed and cached.
         */
        private function exhaustIterator()
        {
            while (! $this->iteratorExhausted) {
                $this->next();
            }
        }

        /**
         * Stores the current item in the cache.
         */
        private function storeCurrentItem()
        {
            $key = $this->iterator->key();

            if ($key === null) {
                return;
            }

            $this->items[$key] = $this->iterator->current();
        }
    }
