<?php

/**
 * Exports patient demographics to a custom XML format
 *
 * @package OpenEMR
 * @link    https://www.open-emr.org
 * @author  Rod Roark <rod@sunsetsystems.com>
 * @author  Roberto Vasquez <robertogagliotta@gmail.com>
 * @copyright Copyright (c) 2005 Rod Roark <rod@sunsetsystems.com>
 * @copyright Copyright (c) 2017 Roberto Vasquez <robertogagliotta@gmail.com>
 * @license https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
*/

 require_once("../interface/globals.php");

 use OpenEMR\Common\Acl\AccessDeniedHelper;
 use OpenEMR\Common\Acl\AclMain;
 use OpenEMR\Core\Header;

if (!AclMain::aclCheckCore('patients', 'demo')) {
    AccessDeniedHelper::denyWithTemplate("ACL check failed for patients/demo: Export Patient Demographics XML", xl("Export Patient Demographics XML"));
}

 $out = "";
 $indent = 0;

 // Add a string to output with some basic sanitizing.
function custom_xml_Add($tag, $text): void
{
    global $out, $indent;
    $text = trim(str_replace(["\r", "\n", "\t"], " ", ($text ?? '')));
    if ($text) {
        for ($i = 0; $i < $indent; ++$i) {
            $out .= "\t";
        }

        $out .= "<$tag>" . xmlEscape($text) . "</$tag>\n";
    }
}


 // Add an insurance section.
function addInsurance($row, $seq): void
{
    if ($row["name$seq"]) {
        OpenTag("insurance");
        custom_xml_Add("priority", $seq);
        custom_xml_Add("group", $row["group$seq"]);
        custom_xml_Add("policy", $row["policy$seq"]);
        custom_xml_Add("provider", $row["provider$seq"]);
        custom_xml_Add("name", $row["name$seq"]);
        custom_xml_Add("street1", $row["street1$seq"]);
        custom_xml_Add("street2", $row["street2$seq"]);
        custom_xml_Add("city", $row["city$seq"]);
        custom_xml_Add("state", $row["state$seq"]);
        custom_xml_Add("zip", $row["zip$seq"]);
        custom_xml_Add("country", $row["country$seq"]);
        custom_xml_Add("type", $row["instype$seq"]);
        custom_xml_Add("copay", $row["copay$seq"]);
        OpenTag("subscriber");
        custom_xml_Add("relationship", $row["relationship$seq"]);
        custom_xml_Add("lname", $row["lname$seq"]);
        custom_xml_Add("fname", $row["fname$seq"]);
        custom_xml_Add("mname", $row["mname$seq"]);
        custom_xml_Add("street", $row["sstreet$seq"]);
        custom_xml_Add("city", $row["scity$seq"]);
        custom_xml_Add("state", $row["sstate$seq"]);
        custom_xml_Add("zip", $row["szip$seq"]);
        custom_xml_Add("country", $row["scountry$seq"]);
        custom_xml_Add("dob", $row["sdob$seq"]);
        custom_xml_Add("ss", $row["sss$seq"]);
        custom_xml_Add("phone", $row["sphone$seq"]);
        custom_xml_Add("employer", $row["semployer$seq"]);
        custom_xml_Add("sex", $row["ssex$seq"]);
        custom_xml_Add("employer_street", $row["semployer_street$seq"]);
        custom_xml_Add("employer_city", $row["semployer_city$seq"]);
        custom_xml_Add("employer_state", $row["semployer_state$seq"]);
        custom_xml_Add("employer_zip", $row["semployer_zip$seq"]);
        custom_xml_Add("employer_country", $row["semployer_country$seq"]);
        CloseTag("subscriber");
        CloseTag("insurance");
    }
}

 // This mess gets all the info for the patient.
 //~Well, now it does...-Art
 $insrow = [];
foreach (['primary','secondary','tertiary'] as $value) {
    $insrow[] = \OpenEMR\Services\Insurance\PolicyRecords::latestIdAtLevel($pid, $value);
}

// The policies' columns (the coverage service), each company's name and type (the payer door) and the
// company's first address, as the one query that joined them took its first row.
/**
 * @param array<string, string> $policyColumns column => the alias's stem (the policy's number $n follows)
 * @param array<string, string> $addressColumns column => alias
 * @return array<mixed>
 */
$exportPolicy = static function (mixed $policyId, int $n, array $policyColumns, array $addressColumns): array {
    $select = [];
    $none = [];
    foreach ($policyColumns as $column => $alias) {
        if (!is_string($column) || !is_string($alias)) {
            continue; // literals only, below
        }
        $select[] = "insd.$column AS $alias$n";
        $none["$alias$n"] = null;
    }
    $policy = \OpenEMR\Services\Insurance\PolicyRecords::columnsById($policyId, implode(', ', $select));
    $out = is_array($policy) ? $policy : $none;
    $company = is_array($policy) ? \OpenEMR\Services\InsuranceCompanyService::columnsOf($policy["provider$n"], "id, name AS name$n, ins_type_code AS instype$n") : false;
    $companyId = is_array($company) ? $company['id'] : null;
    if (is_array($company)) {
        unset($company['id']);
    }
    $out += is_array($company) ? $company : ["name$n" => null, "instype$n" => null];
    $select = [];
    $none = [];
    foreach ($addressColumns as $column => $alias) {
        if (!is_string($column) || !is_string($alias)) {
            continue; // literals only, below
        }
        $select[] = "$column AS $alias";
        $none[$alias] = null;
    }
    $address = $companyId === null ? false : \OpenEMR\Common\Database\QueryUtils::querySingleRow("SELECT " . implode(', ', $select) . " FROM addresses WHERE foreign_id = ? LIMIT 1", [$companyId]);
    return $out + (is_array($address) ? $address : $none);
};
$xmlPolicy = ['policy_number' => 'policy', 'group_number' => 'group', 'provider' => 'provider',
    'subscriber_fname' => 'fname', 'subscriber_mname' => 'mname', 'subscriber_lname' => 'lname',
    'subscriber_street' => 'sstreet', 'subscriber_city' => 'scity', 'subscriber_state' => 'sstate',
    'subscriber_postal_code' => 'szip', 'subscriber_relationship' => 'relationship',
    'subscriber_DOB' => 'sdob', 'subscriber_ss' => 'sss', 'subscriber_phone' => 'sphone',
    'subscriber_sex' => 'ssex', 'subscriber_country' => 'scountry',
    'subscriber_employer' => 'semployer', 'subscriber_employer_street' => 'semployer_street',
    'subscriber_employer_city' => 'semployer_city', 'subscriber_employer_state' => 'semployer_state',
    'subscriber_employer_postal_code' => 'semployer_zip', 'subscriber_employer_country' => 'semployer_country',
    'copay' => 'copay'];
// The secondary and tertiary leave out the subscriber's street, city and state, as the query did.
$xmlPolicyOther = array_diff_key($xmlPolicy, array_flip(['subscriber_street', 'subscriber_city', 'subscriber_state']));
$row = sqlFetchArray(sqlStatement("SELECT p.* FROM patient_data AS p WHERE p.pid = ? LIMIT 1", [$pid]));
if (is_array($row)) {
    foreach ([1, 2, 3] as $n) {
        $row += $exportPolicy($insrow[$n - 1]['id'] ?? null, $n, $n === 1 ? $xmlPolicy : $xmlPolicyOther, [
            'line1' => "street1$n", 'line2' => "street2$n", 'city' => "city$n", 'state' => "state$n",
            'zip' => "zip$n", 'plus_four' => "zip4$n", 'country' => "country$n"]);
    }
}

 $rowed = getEmployerData($pid);

 OpenTag("patient");

 // Patient Section.
 //
 custom_xml_Add("pid", $pid);
 custom_xml_Add("pubpid", $row['pubpid']);
 custom_xml_Add("lname", $row['lname']);
 custom_xml_Add("fname", $row['fname']);
 custom_xml_Add("mname", $row['mname']);
 custom_xml_Add("title", $row['title']);
 custom_xml_Add("ss", Digits($row['ss']));
 custom_xml_Add("dob", fixDate($row['DOB']));
 custom_xml_Add("sex", hl7Sex($row['sex']));
 custom_xml_Add("street", $row['street']);
 custom_xml_Add("city", $row['city']);
 custom_xml_Add("state", $row['state']);
 custom_xml_Add("zip", $row['postal_code']);
 custom_xml_Add("country", $row['country_code']);
 custom_xml_Add("phone_home", Digits($row['phone_home']));
 custom_xml_Add("phone_biz", Digits($row['phone_biz']));
 custom_xml_Add("phone_contact", Digits($row['phone_contact']));
 custom_xml_Add("phone_cell", Digits($row['phone_cell']));
 custom_xml_Add("occupation", $row['occupation']);
 custom_xml_Add("status", $row['status']);
 custom_xml_Add("contact_relationship", $row['contact_relationship']);
 custom_xml_Add("referrer", $row['referrer']);
 custom_xml_Add("referrerID", $row['referrerID']);
 custom_xml_Add("email", $row['email']);
 custom_xml_Add("language", $row['language']);
 custom_xml_Add("ethnoracial", $row['ethnoracial']);
 custom_xml_Add("interpreter", $row['interpreter']);
 custom_xml_Add("migrantseasonal", $row['migrantseasonal']);
 custom_xml_Add("family_size", $row['family_size']);
 custom_xml_Add("monthly_income", $row['monthly_income']);
 custom_xml_Add("homeless", $row['homeless']);
 custom_xml_Add("financial_review", fixDate(substr((string) $row['financial_review'], 0, 10)));
 custom_xml_Add("genericname1", $row['genericname1']);
 custom_xml_Add("genericval1", $row['genericval1']);
 custom_xml_Add("genericname2", $row['genericname2']);
 custom_xml_Add("genericval2", $row['genericval2']);
 custom_xml_Add("billing_note", $row['billing_note']);
 custom_xml_Add("hipaa_mail", $row['hipaa_mail']);
 custom_xml_Add("hipaa_voice", $row['hipaa_voice']);

 // Insurance Sections.
 //
 addInsurance($row, '1');
 addInsurance($row, '2');
 addInsurance($row, '3');

 // Primary Care Physician Section.
 //
if ($row['providerID']) {
    $query = "select id, fname, mname, lname from users where authorized = 1";
    $query .= " AND id = ?";
    $prow = sqlFetchArray(sqlStatement($query, [$row['providerID']]));
    OpenTag("pcp");
    custom_xml_Add("id", $prow['id']);
    custom_xml_Add("lname", $prow['lname']);
    custom_xml_Add("fname", $prow['fname']);
    custom_xml_Add("mname", $prow['mname']);
    CloseTag("pcp");
}

 // Employer Section.
 //
if (!empty($rowed['id'])) {
    OpenTag("employer");
    custom_xml_Add("name", $rowed['name']);
    custom_xml_Add("street", $rowed['street']);
    custom_xml_Add("zip", $rowed['postal_code']);
    custom_xml_Add("city", $rowed['city']);
    custom_xml_Add("state", $rowed['state']);
    custom_xml_Add("country", $rowed['country']);
    CloseTag("employer");
}

 // All done.
 CloseTag("patient");

 // header('Content-type: text/xml');
 // header('Content-Disposition: attachment; filename="pid' . $pid . '.xml"');
 // echo $out;
?>
<html>
<head>
<?php Header::setupHeader(); ?>
<title><?php echo xlt('Export Patient Demographics XML'); ?></title>
</head>
<body class="body_top">
  <div class="container">
     <div class="row">
        <div class="col-12">
           <div class="form-group"></div>
           <div class="form-group">
              <textarea name="export_data" class="form-control" rows="18" readonly><?php echo text($out) ?></textarea>
           </div>
           <div class="form-group">
              <div class="col-12 text-right">
                 <div class="btn-group" role="group">
                    <button type="button" class="btn btn-secondary btn-cancel" onclick="dlgclose()"><?php echo xlt("Close"); ?></button>
                 </div>
              </div>
           </div>
        </div>
     </div>
  </div>

</body>
</html>
