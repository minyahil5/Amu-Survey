<?php


require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db_connect.php';






if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'administrator') {
    http_response_code(403); die("ACCESS DENIED: Admin permission required.");
}

$survey_id = isset($_GET['survey_id']) ? (int)$_GET['survey_id'] : 0;
$format = isset($_GET['format']) ? strtolower(trim($_GET['format'])) : 'pdf';

if ($survey_id <= 0) { die("Error: Invalid Survey ID."); }
if (!$conn || $conn->connect_error) { die("Critical Database Error. Cannot generate report."); }


$survey = null; $stmt_st = $conn->prepare("SELECT title FROM surveys WHERE survey_id = ?");
if ($stmt_st) { $stmt_st->bind_param("i", $survey_id); $stmt_st->execute(); $res_st = $stmt_st->get_result(); $survey = $res_st->fetch_assoc(); $stmt_st->close(); }
if (!$survey) { die("Survey not found."); }
$survey_title = $survey['title'];


$questions_map = []; 
$sql_q = "SELECT question_id, question_text, question_type, order_in_survey FROM questions WHERE survey_id = ? ORDER BY order_in_survey ASC, question_id ASC";
$stmt_q = $conn->prepare($sql_q);
if ($stmt_q) { $stmt_q->bind_param("i", $survey_id); $stmt_q->execute(); $res_q = $stmt_q->get_result(); while ($qr = $res_q->fetch_assoc()){ $questions_map[$qr['question_id']] = $qr; } $stmt_q->close();
} else { error_log("GenReport: QPrepFail - ".$conn->error); die("Error fetching questions."); }


$responses_data = [];
$sql_r = "SELECT rs.response_summary_id, rs.submitted_at, rs.is_anonymous, u.username as resp_username FROM survey_responses_summary rs LEFT JOIN users u ON rs.respondent_user_id = u.user_id WHERE rs.survey_id = ? AND rs.response_status = 'completed' ORDER BY rs.submitted_at ASC";
$stmt_r = $conn->prepare($sql_r);
if($stmt_r){ $stmt_r->bind_param("i", $survey_id); $stmt_r->execute(); $res_r = $stmt_r->get_result();
    while($sr = $res_r->fetch_assoc()){ $ans_for_resp = []; foreach($questions_map as $qid=>$qinfo){$ans_for_resp[$qid]='N/A';} 
        $sql_a = "SELECT a.question_id, a.answer_id, qo.option_text as sel_opt_txt, a.answer_text FROM answers a LEFT JOIN question_options qo ON a.selected_option_id = qo.option_id WHERE a.response_summary_id = ?";
        $stmt_a = $conn->prepare($sql_a);
        if($stmt_a){ $stmt_a->bind_param("i", $sr['response_summary_id']); $stmt_a->execute(); $res_a = $stmt_a->get_result();
            while($ar = $res_a->fetch_assoc()){ $qid_a = $ar['question_id']; if(isset($questions_map[$qid_a])){
                if($questions_map[$qid_a]['question_type']==='mcq_multiple' && $ar['answer_id']){ $mcq_m_txts=[];
                    $sql_m = "SELECT qo_m.option_text FROM answers_mcq_multiple am_m JOIN question_options qo_m ON am_m.selected_option_id=qo_m.option_id WHERE am_m.answer_id=?";
                    $stmt_m=$conn->prepare($sql_m); if($stmt_m){$stmt_m->bind_param("i",$ar['answer_id']);$stmt_m->execute();$res_m=$stmt_m->get_result();
                    while($mo=$res_m->fetch_assoc()){$mcq_m_txts[]=$mo['option_text'];} $stmt_m->close(); $ans_for_resp[$qid_a]=!empty($mcq_m_txts)?implode('; ',$mcq_m_txts):'N/A(m)';}
                } elseif(!empty($ar['sel_opt_txt'])){$ans_for_resp[$qid_a]=$ar['sel_opt_txt'];} elseif(isset($ar['answer_text'])){$ans_for_resp[$qid_a]=$ar['answer_text'];}
            }} $stmt_a->close();
        } else {error_log("GenReport: AnsDetPrepFail - ".$conn->error);}
        $sr['detailed_answers'] = $ans_for_resp; $responses_data[] = $sr;
    } $stmt_r->close();
} else {error_log("GenReport: RespSummPrepFail - ".$conn->error); die("Error fetching responses.");}

$safe_title = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $survey_title);
$base_filename = "Survey_Report_" . $safe_title . "_ID" . $survey_id . "_" . date("Ymd");


if ($format === 'pdf') {
    $fpdf_path = __DIR__ . '/../libs/fpdf/fpdf.php'; 
    if (!file_exists($fpdf_path)) { die("FPDF library not found. Cannot generate PDF."); }
    require_once $fpdf_path;
    class PDFReport extends FPDF { public $surveyTitlePDF = 'Survey Report'; public $margins = ['l'=>15,'t'=>15,'r'=>15]; function __construct($o='P',$u='mm',$s='A4'){parent::__construct($o,$u,$s); $this->SetMargins($this->margins['l'],$this->margins['t'],$this->margins['r']);} function Header(){$this->SetFont('Arial','B',15);$this->Cell(0,10,iconv('UTF-8','ISO-8859-1//TRANSLIT//IGNORE',$this->surveyTitlePDF),0,1,'C'); $this->SetFont('Arial','',9);$this->Cell(0,7,'Generated: '.date("Y-m-d H:i"),0,1,'C');$this->Ln(5);} function Footer(){$this->SetY(-15);$this->SetFont('Arial','I',8);$this->Cell(0,10,'Page '.$this->PageNo().'/{nb}',0,0,'C');} function QACell($qtxt,$atxt){$this->SetFont('Arial','B',10);$this->MultiCell(0,6,iconv('UTF-8','ISO-8859-1//TRANSLIT//IGNORE',$qtxt),0,'L');$this->SetFont('Arial','',10);$this->SetFillColor(245,245,245);$this->MultiCell(0,6,iconv('UTF-8','ISO-8859-1//TRANSLIT//IGNORE',(string)$atxt?:'N/A'),0,'L',true);$this->Ln(4);}}
    $pdf=new PDFReport(); $pdf->surveyTitlePDF = 'Report: '.$survey_title; $pdf->AliasNbPages(); $pdf->AddPage();
    if(empty($responses_data)){$pdf->SetFont('Arial','I',11);$pdf->Cell(0,10,'No completed responses for report.',0,1);}
    else{$pdf->SetFont('Arial','B',12);$pdf->Cell(0,10,'Individual Responses ('.count($responses_data).')',0,1,'L');$pdf->Ln(2);
        foreach($responses_data as $i=>$r){ $pdf->SetFont('Arial','BU',11); $rd=$r['is_anonymous']?'Anonymous':htmlspecialchars($r['resp_username']??'UID:'.$r['respondent_user_id']);
            $pdf->Cell(0,8,"Resp #".($i+1)." (ID:".$r['response_summary_id'].") - By: ".iconv('UTF-8','ISO-8859-1//TRANSLIT//IGNORE',$rd)." on ".date("M d,Y H:i",strtotime($r['submitted_at'])),0,1); $pdf->Ln(2);
            foreach($questions_map as $qid=>$qinfo){ $qdt=($qinfo['order_in_survey']??('Q'.$qid)).". ".$qinfo['question_text']; $adt=$r['detailed_answers'][$qid]??'N/A'; $pdf->QACell($qdt,(string)$adt); }
            if($i<count($responses_data)-1){$pdf->Line($pdf->margins['l'],$pdf->GetY()+2,$pdf->GetPageWidth()-$pdf->margins['r'],$pdf->GetY()+2);$pdf->Ln(5);}}}
    $fname_pdf=$base_filename.".pdf"; if(ob_get_length())ob_end_clean(); $pdf->Output('D',$fname_pdf); exit;

} elseif ($format === 'csv') {
    $filename_csv = $base_filename . ".csv";
    if (ob_get_length()) ob_end_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename_csv . '"');
    $output = fopen('php://output', 'w');
    $csv_headers=['Resp. ID','Submitter','Date','Anonymous?']; $q_ids_ordered=[];
    foreach($questions_map as $q_id=>$q_info){ $csv_headers[]=preg_replace("/\r\n|\r|\n|,/"," ",($q_info['order_in_survey']??$q_id).". ".$q_info['question_text']); $q_ids_ordered[]=$q_id; }
    fputcsv($output, $csv_headers);
    if(!empty($responses_data)){foreach($responses_data as $r){ $csv_row=[]; $csv_row[]=$r['response_summary_id'];
        $csv_row[]=$r['is_anonymous']?'Anonymous':($r['resp_username']??'UID:'.$r['respondent_user_id']);
        $csv_row[]=date("Y-m-d H:i:s",strtotime($r['submitted_at'])); $csv_row[]=$r['is_anonymous']?'Yes':'No';
        foreach($q_ids_ordered as $qid){$ans_d=$r['detailed_answers'][$qid]??''; $csv_row[]=preg_replace("/\r\n|\r|\n/"," ",(string)$ans_d);}
        fputcsv($output, $csv_row);}}
    fclose($output); exit;

} else { die("Invalid report format specified. Supported: pdf, csv."); }
?>