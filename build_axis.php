<?php

/**
 * @return array
 */
function fetchCsv()
{
    $file = new SplFileObject(__DIR__ . '/input.txt');
    $csv_arr = [];

    for ($i = 0; !$file->eof(); $i++) {
        $raw = $file->fgetcsv("\t");
        if ($raw[0]) {
            $csv_arr[$i] = $raw;
        }
    }
    return $csv_arr;
}

/**
 * @return array
 */
function makeAxisNameTable()
{
    $csv = fetchCsv();
    $all_names = [];
    foreach ($csv as $value) {
        $axis_names = array_slice(explode('_', $value[0]), 2);
        $all_names = array_merge($all_names, $axis_names);
    }
    $axis_names_unique = array_values(array_unique($all_names));

    $id = 1;
    foreach ($axis_names_unique as $name) {
        $axis_name_table[$id]['id'] = $id;
        $axis_name_table[$id]['name'] = $name;
        $id++;
    }
    return $axis_name_table;
}

/**
 * アンダーバー区切りのnameの文字列をname_idの配列に変換
 * @param string
 * @return array
 */
function tranceNameToId(string $name)
{
    $name_table = makeAxisNameTable();
    $trance_table = array_combine(array_column($name_table, 'name'), array_column($name_table, 'id'));
    $exolpde_names = array_slice(explode('_', $name), 2);
    $result = [];
    foreach ($exolpde_names as $name) {
        $result[] = $trance_table[$name];
    }
    return $result;
}

/**
 * @return array
 */
function makeAxisTable()
{
    $csv = fetchCsv();
    $axis_table = [];
    $id = 1;

    foreach ($csv as $csv_key => $raw) {
        $name_ids =  tranceNameToId($raw[0]);
        $names = explode('_', $raw[0]);
        foreach ($name_ids as $key => $name_id) {
            $group_id = $raw[1];
            $source_id = $raw[2];
            $parent_id = searchParent($axis_table, $name_ids, $key, $group_id, $source_id);
            $composit_unique = findCompositUnique($axis_table, $name_id, $group_id, $source_id, $parent_id);
            $view_names = array_slice($names, 0, $key + 3);

            if (!$composit_unique ||
                ($key == array_key_last($name_ids) && is_null($parent_id))) {
                $axis_table[$id]['id'] = $id;
                $axis_table[$id]['parent_id'] = $parent_id;
                $axis_table[$id]['name_id'] = $name_id;
                $axis_table[$id]['group_id'] = $group_id;
                $axis_table[$id]['source_id'] = $source_id;
                $axis_table[$id]['view_name'] = implode('_', $view_names);
                $axis_table[$id]['origin_id'] = $key == array_key_last($name_ids) ? $csv_key + 1 : '';
                $id++;
            } elseif ($key == array_key_last($name_ids)) {
                //複合ユニークが既に存在して、入力行が最後の場合は元ファイルのキーをつける
                //複合ユニークが入力ファイル内で重複している場合、後にある情報で上書きされることに注意
                $rewirite_id = $composit_unique['id'];
                $axis_table[$rewirite_id]['origin_id'] = $csv_key + 1;
            }
        }
    }
    return $axis_table;
}

/**
 * @param array $axis_table
 * @param array $name_ids
 * @param int $current_name_key
 * @param int $group_id
 * @param int $source_id
 * 
 * @return array|null
 */
function searchParent(array $axis_table, array $name_ids, int $current_name_key, int $group_id, int $source_id)
{
    $parent_id = null;
    foreach ($name_ids as $key => $name_id) {
        if ($current_name_key == $key) {
            return $parent_id;
        }
        $parent_id = findCompositUnique($axis_table, $name_id, $group_id, $source_id, $parent_id)['id'];
    }
}
/**
 * 複合ユニークを使って作成中のテーブルから取得対象データを取得
 * @param array $axis_table
 * @param int $name_id
 * @param int|null $parent_id
 * @return array|null
 */
function findCompositUnique(array $axis_table, int $name_id, int $group_id, int $source_id, ?int $parent_id)
{
    foreach ($axis_table as $data) {
        if (
            $data['name_id'] == $name_id &&
            $data['parent_id'] == $parent_id &&
            $data['group_id'] == $group_id &&
            $data['source_id'] == $source_id
        ) {
            return $data;
        }
    }
    return null;
}


$axis_name_table = makeAxisNameTable();
$file_axis_name = new SplFileObject(__DIR__ . '/name_table.csv', 'w');
$file_axis_name->fputcsv(array_keys($axis_name_table[1]), "\t");
foreach ($axis_name_table as $line) {
    $file_axis_name->fputcsv($line, "\t");
}


$axis_table = makeAxisTable();
$file_axis = new SplFileObject(__DIR__ . '/axis_table.csv', 'w');
$file_axis->fputcsv(array_keys($axis_table[1]), "\t");
foreach ($axis_table as $line) {
    $file_axis->fputcsv($line, "\t");
}
