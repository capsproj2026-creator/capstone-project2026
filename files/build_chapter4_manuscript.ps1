# Build Chap-1-4-Manuscript.docx from Chap 1-3 + chapter4_content.txt
$ErrorActionPreference = 'Stop'
$filesDir = 'c:\Users\LIZZIE\Downloads\capstone-project2026-main\capstone-project2026-main\files'
$src = Join-Path $filesDir 'Chap-1-3-Manuscript turn it in passed.docx'
$dst = Join-Path $filesDir 'Chap-1-4-Manuscript.docx'
$contentFile = Join-Path $filesDir 'chapter4_content.txt'

Copy-Item -LiteralPath $src -Destination $dst -Force

$word = New-Object -ComObject Word.Application
$word.Visible = $false
$doc = $null
try {
  $doc = $word.Documents.Open($dst)
  $wdCollapseEnd = 0
  $wdAlignParagraphLeft = 0
  $wdPageBreak = 7
  $wdAutoFitContent = 1

  function Get-EndRange {
    $r = $doc.Content
    $null = $r.Collapse($wdCollapseEnd)
    return $r
  }

  function Add-TextPara([string]$text, [string]$styleName, [bool]$bold = $false) {
    $r = Get-EndRange
    $null = $r.InsertAfter($text + "`r")
    $para = $doc.Paragraphs.Item($doc.Paragraphs.Count)
    try { $para.Style = $styleName } catch { }
    if ($bold) { $para.Range.Font.Bold = 1 } else { $para.Range.Font.Bold = 0 }
    $para.Range.ParagraphFormat.Alignment = $wdAlignParagraphLeft
    if ($styleName -eq 'Normal') {
      try { $para.Range.Font.Name = 'Times New Roman'; $para.Range.Font.Size = 12 } catch { }
    }
  }

  # Page break before Chapter 4
  $r = Get-EndRange
  $null = $r.InsertBreak($wdPageBreak)

  $lines = Get-Content -LiteralPath $contentFile -Encoding UTF8
  $pendingCaption = $null
  $tableRows = New-Object System.Collections.Generic.List[object]
  $inTable = $false

  function Flush-Table {
    param($caption, $rows)
    if ($null -eq $rows -or $rows.Count -eq 0) { return }
    if ($caption) { Add-TextPara $caption 'Normal' $true }
    $nRows = $rows.Count
    $nCols = $rows[0].Count
    $r = Get-EndRange
    $table = $doc.Tables.Add($r, $nRows, $nCols)
    for ($i = 0; $i -lt $nRows; $i++) {
      for ($j = 0; $j -lt $nCols; $j++) {
        $cellText = [string]$rows[$i][$j]
        $cell = $table.Cell($i + 1, $j + 1)
        # Clear default cell end marks carefully
        $cell.Range.Text = $cellText
        $cell.Range.Font.Size = 10
        try { $cell.Range.Font.Name = 'Times New Roman' } catch { }
        if ($i -eq 0) { $cell.Range.Font.Bold = 1 } else { $cell.Range.Font.Bold = 0 }
      }
    }
    try { $table.AutoFitBehavior($wdAutoFitContent) } catch { }
    $after = $table.Range
    $null = $after.Collapse($wdCollapseEnd)
    $null = $after.InsertAfter("`r")
  }

  foreach ($raw in $lines) {
    $line = $raw.TrimEnd()
    if ([string]::IsNullOrWhiteSpace($line)) { continue }

    if ($line.StartsWith('CAPTION|')) {
      if ($inTable -and $tableRows.Count -gt 0) {
        Flush-Table $pendingCaption $tableRows
        $tableRows = New-Object System.Collections.Generic.List[object]
        $inTable = $false
      }
      $pendingCaption = $line.Substring(8)
      continue
    }

    if ($line.StartsWith('TABLE|')) {
      if ($inTable -and $tableRows.Count -gt 0) {
        Flush-Table $pendingCaption $tableRows
        $tableRows = New-Object System.Collections.Generic.List[object]
        $pendingCaption = $null
      }
      $inTable = $true
      $cols = $line.Substring(6).Split('|')
      $tableRows.Add($cols) | Out-Null
      continue
    }

    if ($line.StartsWith('ROW|')) {
      $cols = $line.Substring(4).Split('|')
      $tableRows.Add($cols) | Out-Null
      continue
    }

    # Non-table directive: flush any open table first
    if ($inTable -and $tableRows.Count -gt 0) {
      Flush-Table $pendingCaption $tableRows
      $tableRows = New-Object System.Collections.Generic.List[object]
      $pendingCaption = $null
      $inTable = $false
    }

    $pipe = $line.IndexOf('|')
    if ($pipe -lt 0) { continue }
    $tag = $line.Substring(0, $pipe)
    $text = $line.Substring($pipe + 1)

    switch ($tag) {
      'H1' { Add-TextPara $text 'Heading 1' $false }
      'H2' { Add-TextPara $text 'Heading 2' $false }
      'H3' { Add-TextPara $text 'Heading 3' $false }
      'P'  { Add-TextPara $text 'Normal' $false }
      'B'  { Add-TextPara $text 'Normal' $true }
      default { Add-TextPara $text 'Normal' $false }
    }
  }

  if ($inTable -and $tableRows.Count -gt 0) {
    Flush-Table $pendingCaption $tableRows
  }

  try { $null = $doc.Fields.Update() } catch { }
  $doc.Save()
  Write-Host "Saved: $dst"
}
finally {
  if ($doc) { $doc.Close() }
  $word.Quit()
  [System.Runtime.Interopservices.Marshal]::ReleaseComObject($word) | Out-Null
  [GC]::Collect()
}

Get-Item -LiteralPath $dst | Format-List FullName, Length, LastWriteTime
