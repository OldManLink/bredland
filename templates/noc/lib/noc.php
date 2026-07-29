<?php
/**
 * Coordinates the construction and rendering of the Network Operations Centre.
 *
 * Noc forms the application boundary for the dashboard. It owns the
 * collaboration between the classes required to load client state and render
 * the dashboard, but delegates each responsibility to specialised objects.
 */
class Noc {
    private $party_mode;
    private $dashboard;

    /**
     * Returns declarative method contracts only.
     *
     * Maps method names to argument compiler classes.
     * Receivers must not implement compilation logic here.
     */
    public static function compilable_methods() {
        return array(
            'setPartyMode' => BoolVal::class
        );
    }

    public function __construct($dashboard) {
        $this->dashboard = $dashboard;
    }

    public function party_mode() {
        return $this->party_mode;
    }

    public function setPartyMode($party_mode) {
        $this->party_mode = $party_mode;
    }

    public function render() {
        return $this->dashboard->render();
    }
}
