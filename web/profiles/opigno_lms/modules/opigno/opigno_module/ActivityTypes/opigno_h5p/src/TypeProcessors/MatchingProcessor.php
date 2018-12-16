<?php

namespace Drupal\opigno_h5p\TypeProcessors;

/**
 * Class MatchingProcessor
 */
class MatchingProcessor extends TypeProcessor {

  /**
   * Pattern for separating between expressions.
   */
  const EXPRESSION_SEPARATOR = '[,]';

  /**
   * Pattern for separating between matching elements
   */
  const MATCH_SEPARATOR = '[.]';


  /**
   * Processes xAPI data and returns a human readable HTML report
   *
   * @inheritdoc
   */
  function generateHTML($description, $crp, $response, $extras = NULL, $scoreSettings = NULL) {
    // We need some style for our report
    $this->setStyle('opigno_h5p/opigno_h5p.matching');

    $dropzones = $this->getDropzones($extras);
    $draggables = $this->getDraggables($extras);

    $mappedCRP = $this->mapPatternIDsToIndexes($crp[0],
      $dropzones,
      $draggables);

    $mappedResponse = $this->mapPatternIDsToIndexes($response,
      $dropzones,
      $draggables);

    if (empty($mappedCRP) && empty($mappedResponse)) {
      return '';
    }

    $header = $this->generateHeader($description, $scoreSettings);
    $tableHTML = $this->generateTable($mappedCRP,
      $mappedResponse,
      $dropzones,
      $draggables
    );
    $container = '<div class="h5p-reporting-container h5p-matching-container">' .
                   $header . $tableHTML .
                 '</div>';

    return $container;
  }

  /**
   * Generate header element
   *
   * @param $description
   * @param $scoreSettings
   *
   * @return string
   */
  private function generateHeader($description, $scoreSettings) {
    $descriptionHtml = $this->generateDescription($description);
    $scoreHtml = $this->generateScoreHtml($scoreSettings);

    return
      "<div class='h5p-matching-header'>" .
        $descriptionHtml . $scoreHtml .
      "</div>";
  }

  /**
   * Generate description element
   *
   * @param string $description
   *
   * @return string Description element as a string
   */
  private function generateDescription($description) {
    return
      '<p class="h5p-reporting-description h5p-matching-task-description">' .
        $description .
      '</p>';
  }

  /**
   * Create a map that links IDs from pattern to indexes in the droppable and
   * draggable arrays.
   *
   * @param string $pattern
   * @param array $dropzoneIds
   * @param array $draggableIds
   *
   * @return array Pattern mapped to indexes instead of IDs
   */
  function mapPatternIDsToIndexes($pattern, $dropzoneIds, $draggableIds) {
    $mappedMatches = array();
    if (empty($pattern)) {
      return $mappedMatches;
    }

    $singlePatterns = explode(self::EXPRESSION_SEPARATOR, $pattern);
    foreach($singlePatterns as $singlePattern) {
      $matches = explode(self::MATCH_SEPARATOR, $singlePattern);

      // ID does not necessarily map to index, so we must remap it
      $dropzoneId = $this->findIndexOfItemWithId($dropzoneIds, $matches[0]);
      $draggableId = $this->findIndexOfItemWithId($draggableIds, $matches[1]);

      if (!isset($mappedMatches[$dropzoneId])) {
        $mappedMatches[$dropzoneId] = array();
      }

      $mappedMatches[$dropzoneId][] = $draggableId;
    }

    return $mappedMatches;
  }

  /**
   * Find id of an item with a given index inside given array
   *
   * @param array $haystack
   * @param number $id
   *
   * @return number Id of mapped item
   */
  function findIndexOfItemWithId($haystack, $id) {
    return (isset($haystack[$id]) ? $haystack[$id]->id : NULL);
  }

  /**
   * Generate table from user response, correct response pattern, dropzones and
   * draggables
   *
   * @param array $mappedCRP
   * @param array $mappedResponse
   * @param array $dropzones
   * @param array $draggables
   *
   * @return string Table element
   */
  function generateTable($mappedCRP, $mappedResponse, $dropzones, $draggables) {
    $header = $this->generateTableHeader();
    $rows = $this->generateRows($mappedCRP, $mappedResponse, $dropzones,
      $draggables);

    return '<table class="h5p-matching-table">' . $header . $rows . '</table>';
  }

  /**
   * Generate rows of table
   *
   * @param array $mappedCRP
   * @param array $mappedResponse
   * @param array $dropzones
   * @param array $draggables
   *
   * @return string HTML for generated table rows
   */
  function generateRows($mappedCRP, $mappedResponse, $dropzones, $draggables) {
    $html = '';
    foreach($dropzones as $index => $value) {
      $html .= $this->generateDropzoneRows($value,
        $draggables,
        isset($mappedCRP[$index]) ? $mappedCRP[$index] : array(),
        isset($mappedResponse[$index]) ? $mappedResponse[$index] : array()
      );
    }
    return $html;
  }

  /**
   * Generate row for a single dropzone and populate it with correct answers and
   * user answers
   *
   * @param object $dropzone
   * @param array $draggables
   * @param array $crp
   * @param array $response
   *
   * @return string Drop zone rows element
   */
  function generateDropzoneRows($dropzone, $draggables, $crp, $response) {
    $dzRows = sizeof($crp) > sizeof($response) ? sizeof($crp) : sizeof($response);

    // Skip row if no correct or user answers
    if ($dzRows <= 0) {
      return '';
    }

    $rows = '';
    $lastCellInRow = 'h5p-matching-last-cell-in-row';

    for ($i = 0; $i < $dzRows; $i++) {
      $row = '';
      $tdClass = $i >= $dzRows - 1 ? $lastCellInRow : '';

      if ($i === 0) {
        // Add drop zone
        $row .=
          '<th class="' . 'h5p-matching-dropzone ' . $lastCellInRow . '"' .
            ' rowspan="' . $dzRows . '"' .
          '>' .
            $dropzone->value .
          '</th>';
      }

      // Add correct response pattern
      $crpCellContent = isset($crp[$i]) ? $draggables[$crp[$i]]->value : '';
      $row .= '<td class="' . $tdClass . '">' .
                $crpCellContent .
              '</td>';


      // Add user response
      $isCorrectClass = '';
      $responseCellContent = '';
      if (isset($response[$i])) {
        $isCorrectClass = isset($response[$i]) && in_array($response[$i], $crp) ?
          'h5p-matching-draggable-correct' : 'h5p-matching-draggable-wrong';
        foreach ($draggables as $draggable) {
          if ($draggable->id === $response[$i]) {
            $responseCellContent = $draggable->value;
            break;
          }
        }
      }

      $classes = $tdClass . (sizeof($isCorrectClass) ? ' ' : '') . $isCorrectClass;
      $row .= '<td class="' . $classes . '">' .
                $responseCellContent .
              '</td>';

      $rows .= '<tr>' . $row . '</tr>';
    }
    return $rows;
  }

  /**
   * Generate table header
   *
   * @return string Table header element as a string
   */
  function generateTableHeader() {
    // Empty first item
    $html = '<th class="h5p-matching-header-dropzone">Dropzone</th>' .
            '<th class="h5p-matching-header-correct">Correct Answers</th>' .
            '<th class="h5p-matching-header-user">Your answers</th>';

    return '<tr class="h5p-matching-table-heading">' . $html . '</tr>';
  }

  /**
   * Extract drop zones from extras parameters
   *
   * @param object $extras
   *
   * @return array Drop zones
   */
  function getDropzones($extras) {
    $dropzones = array();

    foreach($extras->target as $value) {
      $dropzones[] = (object) array(
        'id' => $value->id,
        'value' => $value->description->{'en-US'}
      );
    }

    return $dropzones;
  }

  /**
   * Extract draggables from extras parameters
   *
   * @param object $extras
   *
   * @return array Draggables
   */
  function getDraggables($extras) {
    $draggables = array();

    foreach($extras->source as $value) {
      $draggables[] = (object) array(
        'id' => $value->id,
        'value' => $value->description->{'en-US'}
      );
    }

    return $draggables;
  }
}
