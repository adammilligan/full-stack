<?php

class User {

    // GENERAL

    public static function user_info($d) {
        // vars
        $user_id = isset($d['user_id']) && is_numeric($d['user_id']) ? $d['user_id'] : 0;
        $phone = isset($d['phone']) ? preg_replace('~\D+~', '', $d['phone']) : 0;
        // where
        if ($user_id) $where = "user_id='".$user_id."'";
        else if ($phone) $where = "phone='".$phone."'";
        else return [];
        // info
        $q = DB::query("SELECT user_id, phone, access FROM users WHERE ".$where." LIMIT 1;") or die (DB::error());
        if ($row = DB::fetch_row($q)) {
            return [
                'id' => (int) $row['user_id'],
                'access' => (int) $row['access']
            ];
        } else {
            return [
                'id' => 0,
                'access' => 0
            ];
        }
    }

    public static function users_list_plots($number) {
        // vars
        $items = [];
        // info
        $q = DB::query("SELECT user_id, plot_id, first_name, email, phone
            FROM users WHERE plot_id LIKE '%".$number."%' ORDER BY user_id;") or die (DB::error());
        while ($row = DB::fetch_row($q)) {
            $plot_ids = explode(',', $row['plot_id']);
            $val = false;
            foreach($plot_ids as $plot_id) if ($plot_id == $number) $val = true;
            if ($val) $items[] = [
                'id' => (int) $row['user_id'],
                'first_name' => $row['first_name'],
                'email' => $row['email'],
                'phone_str' => phone_formatting($row['phone'])
            ];
        }
        // output
        return $items;
    }
    public static function users_list($d = []) {
            // vars
            $search = isset($d['search']) && trim($d['search']) ? $d['search'] : '';
            $offset = isset($d['offset']) && is_numeric($d['offset']) ? $d['offset'] : 0;
            $limit = 20;
            $items = [];
            // where
            $where = [];
            if ($search) $where[] = "number LIKE '%".$search."%'";
            $where = $where ? "WHERE ".implode(" AND ", $where) : "";
            // info
            $q = DB::query("SELECT plot_id, status, billing, number, size, price, base_fixed, electricity_t1, electricity_t2, updated
                FROM plots ".$where." ORDER BY number+0 LIMIT ".$offset.", ".$limit.";") or die (DB::error());
            while ($row = DB::fetch_row($q)) {
                $items[] = [
                    'id' => (int) $row['plot_id'],
                    'status' => $row['status'],
                    'status_str' => Plot::plot_status_str($row['status']),
                    'billing' => $row['billing'],
                    'number' => $row['number'],
                    'size' => $row['size'],
                    'price' => number_format($row['price'], 0, '', ' '),
                    'base_fixed' => (bool) $row['base_fixed'],
                    'electricity_t1' => (float) $row['electricity_t1'],
                    'electricity_t2' => (float) $row['electricity_t2'],
                    'users' => $row['number'] ? User::users_list_plots($row['number']) : [],
                    'updated' => date('Y/m/d', $row['updated'])
                ];
            }
            // paginator
            $q = DB::query("SELECT count(*) FROM plots ".$where.";");
            $count = ($row = DB::fetch_row($q)) ? $row['count(*)'] : 0;
            $url = 'plots';
            if ($search) $url .= '?search='.$search.'&';
            paginator($count, $offset, $limit, $url, $paginator);
            // output
            return ['items' => $items, 'paginator' => $paginator];
        }

        public static function users_fetch($d = []) {
            $info = Plot::plots_list($d);
            HTML::assign('plots', $info['items']);
            return ['html' => HTML::fetch('./partials/plots_table.html'), 'paginator' => $info['paginator']];
        }

        // ACTIONS

        public static function user_edit_window($d = []) {
            $plot_id = isset($d['plot_id']) && is_numeric($d['plot_id']) ? $d['plot_id'] : 0;
            HTML::assign('plot', Plot::plot_info($plot_id));
            return ['html' => HTML::fetch('./partials/plot_edit.html')];
        }

        public static function user_edit_update($d = []) {
            // vars
            $plot_id = isset($d['plot_id']) && is_numeric($d['plot_id']) ? $d['plot_id'] : 0;
            $status = isset($d['status']) && is_numeric($d['status']) ? $d['status'] : 0;
            $billing = isset($d['billing']) && in_array($d['billing'], [0,1]) ? $d['billing'] : 0;
            $number = isset($d['number']) && trim($d['number']) ? trim($d['number']) : '';
            $size = isset($d['size']) ? preg_replace('~\D+~', '', $d['size']) : 0;
            $price = isset($d['price']) ? preg_replace('~\D+~', '', $d['price']) : 0;
            $offset = isset($d['offset']) ? preg_replace('~\D+~', '', $d['offset']) : 0;
            // update
            if ($plot_id) {
                $set = [];
                $set[] = "status='".$status."'";
                $set[] = "billing='".$billing."'";
                $set[] = "number='".$number."'";
                $set[] = "size='".$size."'";
                $set[] = "price='".$price."'";
                $set[] = "updated='".Session::$ts."'";
                $set = implode(", ", $set);
                DB::query("UPDATE plots SET ".$set." WHERE plot_id='".$plot_id."' LIMIT 1;") or die (DB::error());
            } else {
                DB::query("INSERT INTO plots (
                    status,
                    billing,
                    number,
                    size,
                    price,
                    updated
                ) VALUES (
                    '".$status."',
                    '".$billing."',
                    '".$number."',
                    '".$size."',
                    '".$price."',
                    '".Session::$ts."'
                );") or die (DB::error());
            }
            // output
            return Plot::plots_fetch(['offset' => $offset]);
        }

        private static function user_status_str($id) {
            if ($id == 1) return 'Reserved';
            if ($id == 2) return 'Sold';
            return 'Free';
        }
}
