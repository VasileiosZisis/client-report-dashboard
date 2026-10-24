<?php
/**
 * Shared in-memory options adapter for isolated regression runners.
 *
 * @package ClientReportingDashboard
 */

(defined('WP_CLI') && WP_CLI) || exit;

/** In-memory options adapter. No fixture write can reach the site's database. */
final class CLIREDAS_Cache_Fixture_DB
{
    /** Fictional options table.
     *
     * @var string
     */
    public $options = 'cliredas_fixture_options';
    /** Original site prefix for the actor.
     *
     * @var string
     */
    public $prefix = '';
    /** Isolated options.
     *
     * @var array
     */
    public $state = array();
    /** Captured autoload flags.
     *
     * @var array
     */
    public $autoload = array();
    /** Simulated storage failure.
     *
     * @var bool
     */
    public $fail = false;
    /** One-shot concurrent writer.
     *
     * @var callable|null
     */
    public $conflict = null;

    /** Capture SQL parameters without building executable SQL. */
    public function prepare($sql, ...$args)
    {
        return array($sql, $args);
    }

    /** Preserve the preloaded fixture actor. */
    public function get_blog_prefix()
    {
        return $this->prefix;
    }

    /** Read only our fictional options table.
     *
     * @throws RuntimeException On an unexpected read.
     */
    public function get_row($query)
    {
        $key = $query[1][0];
        if (0 !== strpos($key, 'cliredas_') && 'cron' !== $key) {
            throw new RuntimeException('Unexpected fixture read');
        }
        return isset($this->state[$key]) ? (object) array('option_value' => maybe_serialize($this->state[$key])) : null;
    }

    /** Simulate insertion and conditional publication, including concurrent writes. */
    public function query($query)
    {
        if ($this->fail) {
            return 0;
        }
        if (is_callable($this->conflict)) {
            $callback = $this->conflict;
            $this->conflict = null;
            $callback($this);
            return 0;
        }
        list($sql, $args) = $query;
        if (0 === strpos($sql, 'INSERT')) {
            list($key, $value) = $args;
            if (isset($this->state[$key])) {
                return 0;
            }
            $this->state[$key] = maybe_unserialize($value);
            $this->autoload[$key] = isset($args[2]) ? $args[2] : null;
            return 1;
        }
        list($value, $key, $expected) = $args;
        if (! isset($this->state[$key]) || maybe_serialize($this->state[$key]) !== $expected) {
            return 0;
        }
        $this->state[$key] = maybe_unserialize($value);
        return 1;
    }

    /** Capture normal Options API updates, notably WP-Cron scheduling.
     *
     * @throws RuntimeException On an unexpected write.
     */
    public function update($table, $data, $where)
    {
        if ($table !== $this->options) {
            throw new RuntimeException('Unexpected fixture write');
        }
        if ($this->fail) {
            return 0;
        }
        $this->state[$where['option_name']] = maybe_unserialize($data['option_value']);
        return 1;
    }

    /** Capture only option deletion.
     *
     * @throws RuntimeException On an unexpected deletion.
     */
    public function delete($table, $where)
    {
        if ($table !== $this->options) {
            throw new RuntimeException('Unexpected fixture deletion');
        }
        unset($this->state[$where['option_name']]);
        return 1;
    }
}
