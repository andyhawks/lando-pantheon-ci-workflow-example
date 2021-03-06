<?php

namespace Drupal\opigno_learning_path\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\forum\Controller\ForumController;
use Drupal\opigno_moxtra\Entity\Workspace;
use Drupal\taxonomy\Entity\Term;
use Drupal\tft\Controller\TFTController;
use Drupal\opigno_learning_path\LearningPathAccess;

class LearningPathController extends ControllerBase {

  /**
   * @param array $step
   *
   * @return array
   */
  protected function build_step_score_cell($step) {
    if (in_array($step['typology'], ['Module', 'Course', 'Meeting', 'ILT'])) {
      $score = $step['best score'];

      return [
        '#type' => 'container',
        [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#value' => $score . '%',
        ],
        [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['lp_step_result_bar'],
          ],
          [
            '#type' => 'html_tag',
            '#tag' => 'div',
            '#attributes' => [
              'class' => ['lp_step_result_bar_value'],
              'style' => "width: $score%",
            ],
            '#value' => '',
          ],
        ],
      ];
    }
    else {
      return ['#markup' => '&dash;'];
    }
  }

  /**
   * @param array $step
   *
   * @return array
   */
  protected function build_step_state_cell($step) {
    $user = $this->currentUser();
    $uid = $user->id();

    $status = opigno_learning_path_get_step_status($step, $uid);
    switch ($status) {
      case 'pending':
        $markup = '<span class="lp_step_state_pending"></span>' . t('Pending');
        break;

      case 'failed':
        $markup = '<span class="lp_step_state_failed"></span>' . t('Failed');
        break;

      case 'passed':
        $markup = '<span class="lp_step_state_passed"></span>' . t('Passed');
        break;

      default:
        $markup = '&dash;';
        break;
    }

    return ['#markup' => $markup];
  }

  /**
   * @param array $step
   *
   * @return array
   */
  protected function build_course_row($step) {
    $result = $this->build_step_score_cell($step);
    $state = $this->build_step_state_cell($step);

    return [
      $step['name'],
      [
        'class' => 'lp_step_details_result',
        'data' => $result,
      ],
      [
        'class' => 'lp_step_details_state',
        'data' => $state,
      ],
    ];
  }

  /**
   * @return array
   */
  public function progress() {
    /** @var \Drupal\group\Entity\GroupInterface $group */
    $group = \Drupal::routeMatch()->getParameter('group');
    $user = \Drupal::currentUser();

    $id = $group->id();
    $uid = $user->id();

    $progress = opigno_learning_path_progress($id, $uid);
    $progress = round(100 * $progress);

    if (opigno_learning_path_is_passed($group, $uid)) {
      $score = opigno_learning_path_get_score($id, $uid);

      /** @var \Drupal\Core\Datetime\DateFormatterInterface $date_formatter */
      $date_formatter = \Drupal::service('date.formatter');

      $completed = opigno_learning_path_completed_on($id, $uid);
      $completed = $completed > 0
        ? $date_formatter->format($completed, 'custom', 'F d, Y')
        : '';

      $summary = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['lp_progress_summary'],
        ],
        [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#attributes' => [
            'class' => ['lp_progress_summary_passed'],
          ],
          '#value' => '',
        ],
        [
          '#type' => 'html_tag',
          '#tag' => 'h3',
          '#attributes' => [
            'class' => ['lp_progress_summary_title'],
          ],
          '#value' => t('Passed'),
        ],
        [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#attributes' => [
            'class' => ['lp_progress_summary_score'],
          ],
          '#value' => t('Average score : @score%', [
            '@score' => $score,
          ]),
        ],
        [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#attributes' => [
            'class' => ['lp_progress_summary_date'],
          ],
          '#value' => t('Completed on @date', [
            '@date' => $completed,
          ]),
        ],
      ];
    }

    $content = [];
    $content[] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['col-sm-9', 'mb-3'],
      ],
      [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['lp_progress'],
        ],
        [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#attributes' => [
            'class' => ['lp_progress_label'],
          ],
          '#value' => t('Global Training Progress'),
        ],
        [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#attributes' => [
            'class' => ['lp_progress_value'],
          ],
          '#value' => $progress . '%',
        ],
        [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['lp_progress_bar'],
          ],
          [
            '#type' => 'html_tag',
            '#tag' => 'div',
            '#attributes' => [
              'class' => ['lp_progress_bar_completed'],
              'style' => "width: $progress%",
            ],
            '#value' => '',
          ],
        ],
      ],
      isset($summary) ? $summary : [],
      '#attached' => [
        'library' => [
          'opigno_learning_path/training_content',
          'core/drupal.dialog.ajax',
        ],
      ],
    ];

    $continue_route = 'opigno_learning_path.steps.start';
    $edit_route = 'entity.group.edit_form';
    $members_route = 'opigno_learning_path.membership.overview';

    $route_args = ['group' => $group->id()];
    $continue_url = Url::fromRoute($continue_route, $route_args);
    $edit_url = Url::fromRoute($edit_route, $route_args);
    $members_url = Url::fromRoute($members_route, $route_args);

    $admin_continue_button = Link::fromTextAndUrl('', $continue_url)->toRenderable();
    $admin_continue_button['#attributes']['class'][] = 'lp_progress_admin_continue';
    $admin_continue_button['#attributes']['class'][] = 'use-ajax';
    $edit_button = Link::fromTextAndUrl('', $edit_url)->toRenderable();
    $edit_button['#attributes']['class'][] = 'lp_progress_admin_edit';
    $members_button = Link::fromTextAndUrl('', $members_url)->toRenderable();
    $members_button['#attributes']['class'][] = 'lp_progress_admin_edit';

    $continue_button_text = $this->t('Continue Training');
    $continue_button = Link::fromTextAndUrl($continue_button_text, $continue_url)->toRenderable();
    $continue_button['#attributes']['class'][] = 'lp_progress_continue';
    $continue_button['#attributes']['class'][] = 'use-ajax';

    $buttons = [];
    if ($group->access('update', $user)) {
      $buttons[] = $admin_continue_button;
      $buttons[] = $edit_button;
    }
    elseif ($group->access('administer members', $user)) {
      $buttons[] = $admin_continue_button;
      $buttons[] = $members_button;
    }
    else {
      $buttons[] = $continue_button;
    }

    $content[] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['col-sm-3', 'mb-3'],
      ],
      $buttons,
    ];

    return $content;
  }

  /**
   * @return array
   */
  public function trainingContent() {
    /** @var \Drupal\group\Entity\Group $group */
    $group = \Drupal::routeMatch()->getParameter('group');
    $user = \Drupal::currentUser();
    $content = [
      '#attached' => [
        'library' => [
          'core/drupal.dialog.ajax',
        ],
      ],
    ];

    // If not a member.
    if (!$group->getMember($user)
      || !$user->isAuthenticated() && $group->field_learning_path_visibility->value === 'semiprivate') {
      return $content;
    }

    // Check if membership has status 'pending'.
    $visibility = $group->field_learning_path_visibility->value;
    $validation = $group->field_requires_validation->value;
    $account = \Drupal::currentUser();
    $member_pending = $visibility === 'semiprivate' && $validation
      && !LearningPathAccess::statusGroupValidation($group, $account);
    if ($member_pending) return $content;

    $steps = opigno_learning_path_get_steps($group->id(), $user->id());
    $steps = array_filter($steps, function ($step) use ($user) {
      if ($step['typology'] === 'Meeting') {
        // If the user have not the collaborative features role.
        if (!$user->hasPermission('view meeting entities')) {
          return FALSE;
        }

        // If the user is not a member of the meeting.
        /** @var \Drupal\opigno_moxtra\MeetingInterface $meeting */
        $meeting = \Drupal::entityTypeManager()
          ->getStorage('opigno_moxtra_meeting')
          ->load($step['id']);
        if (!$meeting->isMember($user->id())) {
          return FALSE;
        }
      }
      elseif ($step['typology'] === 'ILT') {
        // If the user is not a member of the ILT.
        /** @var \Drupal\opigno_ilt\ILTInterface $ilt */
        $ilt = \Drupal::entityTypeManager()
          ->getStorage('opigno_ilt')
          ->load($step['id']);
        if (!$ilt->isMember($user->id())) {
          return FALSE;
        }
      }

      return TRUE;
    });
    $steps = array_map(function ($step) use ($user) {
      $sub_title = '';
      $score = $this->build_step_score_cell($step);
      $state = $this->build_step_state_cell($step);
      $rows = [];

      if ($step['typology'] === 'Course') {
        $course_steps = opigno_learning_path_get_steps($step['id'], $user->id());
        $sub_title = t('@count Modules', [
          '@count' => count($course_steps),
        ]);

        $rows = array_map([$this, 'build_course_row'], $course_steps);
      }

      $title = $step['name'];

      if ($step['typology'] === 'Meeting') {
        /** @var \Drupal\opigno_moxtra\MeetingInterface $meeting */
        $meeting = $this->entityTypeManager()
          ->getStorage('opigno_moxtra_meeting')
          ->load($step['id']);
        $start_date = $meeting->getStartDate();
        $end_date = $meeting->getEndDate();
      }
      elseif ($step['typology'] === 'ILT') {
        /** @var \Drupal\opigno_ilt\ILTInterface $ilt */
        $ilt = $this->entityTypeManager()
          ->getStorage('opigno_ilt')
          ->load($step['id']);
        $start_date = $ilt->getStartDate();
        $end_date = $ilt->getEndDate();
      }

      if (isset($start_date) && isset($end_date)) {
        $start_date = DrupalDateTime::createFromFormat(
          DrupalDateTime::FORMAT,
          $start_date
        );
        $end_date = DrupalDateTime::createFromFormat(
          DrupalDateTime::FORMAT,
          $end_date
        );

        $title .= ' / ' . $this->t('@start to @end', [
            '@start' => $start_date->format('jS F Y - g:i A'),
            '@end' => $end_date->format('g:i A'),
          ]);
      }

      return [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['lp_step'],
        ],
        [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['lp_step_title_wrapper'],
          ],
          ($step['mandatory']
            ? [
              '#type' => 'html_tag',
              '#tag' => 'span',
              '#attributes' => [
                'class' => ['lp_step_required'],
              ],
              '#value' => '',
            ]
            : []),
          [
            '#type' => 'html_tag',
            '#tag' => 'h3',
            '#attributes' => [
              'class' => ['lp_step_title'],
            ],
            '#value' => $title,
          ],
        ],
        [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['lp_step_content'],
          ],
          [
            '#type' => 'container',
            '#attributes' => [
              'class' => ['lp_step_summary'],
            ],
            [

              '#type' => 'container',
              '#attributes' => [
                'class' => ['lp_step_summary_title_wrapper'],
              ],
              [
                '#type' => 'html_tag',
                '#tag' => 'h3',
                '#attributes' => [
                  'class' => ['lp_step_summary_title'],
                ],
                '#value' => $step['name'],
              ],
              [
                '#type' => 'html_tag',
                '#tag' => 'h4',
                '#attributes' => [
                  'class' => ['lp_step_summary_subtitle'],
                ],
                '#value' => $sub_title,
              ],
              !empty($step['description']) ?
              [
                '#type' => 'html_tag',
                '#tag' => 'div',
                '#attributes' => [
                  'class' => ['lp_step_summary_description'],
                ],
                '#value' => $step['description'],
              ] : [],
            ],
            [
              '#type' => 'table',
              '#attributes' => [
                'class' => ['lp_step_summary_details'],
              ],
              '#header' => [
                t('Score'),
                t('State'),
              ],
              '#rows' => [
                [
                  [
                    'class' => 'lp_step_details_result',
                    'data' => $score,
                  ],
                  [
                    'class' => 'lp_step_details_state',
                    'data' => $state,
                  ],
                ],
              ],
            ],
          ],
          [
            '#type' => 'container',
            '#attributes' => [
              'class' => ['lp_step_details_wrapper'],
            ],
            ($step['typology'] === 'Course'
              ? [
                '#type' => 'table',
                '#attributes' => [
                  'class' => ['lp_step_details'],
                ],
                '#header' => [
                  t('Module'),
                  t('Score'),
                  t('State'),
                ],
                '#rows' => $rows,
              ]
              : []),
          ],
        ],
        ($step['typology'] === 'Course'
        ? [
            [
              '#type' => 'container',
              '#attributes' => [
                'class' => ['lp_step_show'],
              ],
              [
                '#type' => 'html_tag',
                '#tag' => 'span',
                '#attributes' => [
                  'class' => ['lp_step_show_text'],
                ],
                '#value' => t('Show details'),
              ],
            ],
            [
              '#type' => 'container',
              '#attributes' => [
                'class' => ['lp_step_hide'],
              ],
              [
                '#type' => 'html_tag',
                '#tag' => 'span',
                '#attributes' => [
                  'class' => ['lp_step_hide_text'],
                ],
                '#value' => t('Hide details'),
              ],
            ],
          ] : []),
      ];
    }, $steps);

    // $TFTController = new TFTController();
    // $listGroup = $TFTController->listGroup($group->id());
    $tft_url = Url::fromRoute('tft.group', ['group' => $group->id()])->toString();

    $content['tabs'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['lp_tabs', 'nav', 'mb-4']],
    ];

    $content['tabs'][] = [
      '#markup' => '<a class="lp_tabs_link active" data-toggle="tab" href="#training-content">' . t('Training Content') . '</a>',
    ];

    $content['tabs'][] = [
      '#markup' => '<a class="lp_tabs_link" data-toggle="tab" href="#documents-library">' . t('Documents Library') . '</a>',
    ];

    $content['tab-content'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['tab-content']],
    ];

    $content['tab-content'][] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'training-content', 'class' => ['tab-pane', 'fade', 'show', 'active']],
      'steps' => $steps,
    ];

    $content['tab-content'][] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'documents-library', 'class' => ['tab-pane', 'fade']],
      [
        '#type' => 'html_tag',
        '#tag' => 'iframe',
        '#attributes' => [
          'src' => $tft_url,
          'frameborder' => 0,
          'width' => '100%',
          'height' => '600px',
        ]
      ],
      // $listGroup,
    ];

    $is_moxtra_enabled = \Drupal::hasService('opigno_moxtra.workspace_controller');
    if ($is_moxtra_enabled) {
      $has_workspace_field = $group->hasField('field_workspace');
      $has_workspace_access = $user->hasPermission('view workspace entities');
      if ($has_workspace_field && $has_workspace_access) {
        /** @var \Drupal\opigno_moxtra\Controller\WorkspaceController $workspace_controller */
        $workspace_controller = \Drupal::service('opigno_moxtra.workspace_controller');

        if ($group->get('field_workspace')->getValue() &&
          $workspace_id = $group->get('field_workspace')->getValue()[0]['target_id']
        ) {
          $workspace_url = Url::fromRoute('opigno_moxtra.workspace.iframe', ['opigno_moxtra_workspace' => $workspace_id])->toString();

          $content['tabs'][] = [
            '#markup' => '<a class="lp_tabs_link" data-toggle="tab" href="#collaborative-workspace">' . t('Collaborative Workspace') . '</a>',
          ];
        }

        $workspace_tab = [
          '#type' => 'container',
          '#attributes' => [
            'id' => 'collaborative-workspace',
            'class' => ['tab-pane', 'fade'],
          ],
          'content' => [
            '#type' => 'container',
            '#attributes' => [
              'class' => ['row'],
            ],
            (isset($workspace_url)) ? [
              '#type' => 'html_tag',
              '#tag' => 'iframe',
              '#attributes' => [
                'src' => $workspace_url,
                'frameborder' => 0,
                'width' => '100%',
                'height' => '600px',
              ]
            ] : [],
            // 'workspace_list' => [
            //   '#type' => 'container',
            //   '#attributes' => [
            //     'class' => ['col-md-4'],
            //   ],
            //   'content' => $workspace_controller->workspaceList(),
            // ],
            // 'workspace' => [
            //   '#type' => 'container',
            //   '#attributes' => [
            //     'class' => ['col-md-8'],
            //   ],
            //   'content' => [
            //     '#markup' => $this->t('No collaborative workspace was found for this group ! Please contact your system administrator.'),
            //   ],
            // ],
          ],
        ];

        // $workspace_id = $group->get('field_workspace')->getValue()[0]['target_id'];
        // $workspace_url = Url::fromRoute('opigno_moxtra.workspace.iframe', ['opigno_moxtra_workspace' => $workspace_id])->toString();
        // $workspace = Workspace::load($workspace_id);
        // if ($workspace !== NULL) {
        //   $workspace_tab['content']['workspace']['content'] = $workspace_controller->index($workspace);
        // }
        //
        $content['tab-content'][] = $workspace_tab;
      }
    }

    $has_enable_forum_field = $group->hasField('field_learning_path_enable_forum');
    $has_forum_field = $group->hasField('field_learning_path_forum');
    if ($has_enable_forum_field && $has_forum_field) {
      $enable_forum_field = $group->get('field_learning_path_enable_forum')->getValue();
      $forum_field = $group->get('field_learning_path_forum')->getValue();
      if (!empty($enable_forum_field) && !empty($forum_field)) {
        $enable_forum = $enable_forum_field[0]['value'];
        $forum_tid = $forum_field[0]['target_id'];
        if ($enable_forum && _opigno_forum_access($forum_tid, $user)) {
          $forum_term = Term::load($forum_tid);
          $forum_url = Url::fromRoute('forum.page', ['taxonomy_term' => $forum_tid])->toString();
          // $forum_controller = ForumController::create(\Drupal::getContainer());
          // $forum = $forum_controller->forumPage($forum_term);

          $content['tabs'][] = [
            '#markup' => '<a class="lp_tabs_link" data-toggle="tab" href="#forum">' . t('Forum') . '</a>',
          ];

          $content['tab-content'][] = [
            '#type' => 'container',
            '#attributes' => [
              'id' => 'forum',
              'class' => ['tab-pane', 'fade'],
            ],
            [
              '#type' => 'html_tag',
              '#tag' => 'iframe',
              '#attributes' => [
                'src' => $forum_url,
                'frameborder' => 0,
                'width' => '100%',
                'height' => '600px',
              ]
            ],
            // 'forum' => $forum,
          ];
        }
      }
    }

    $content['#attached']['library'][] = 'opigno_learning_path/training_content';

    return $content;
  }

}
