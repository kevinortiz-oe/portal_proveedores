<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class FixNumeroDteType extends Migration
{
    public function up()
    {
        // Force the column to be VARCHAR to accept alphanumeric invoice numbers
        // Using raw query for Postgres compatibility
        $this->db->query("ALTER TABLE facturas ALTER COLUMN numero_dte TYPE VARCHAR(255)");

        // Ensure serie is also VARCHAR
        $this->db->query("ALTER TABLE facturas ALTER COLUMN serie TYPE VARCHAR(100)");
    }

    public function down()
    {
        // Reverting to integer might fail if data contains letters, so we omit strict revert or use USING
        // $this->db->query("ALTER TABLE facturas ALTER COLUMN numero_dte TYPE INTEGER USING numero_dte::integer");
    }
}
