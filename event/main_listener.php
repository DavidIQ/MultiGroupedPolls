<?php
/**
 *
 * Advanced Polls. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2016, David Colón, http://www.davidiq.com
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace davidiq\MultiGroupedPolls\event;

/**
 * @ignore
 */
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Multi-Grouped Polls Event listener.
 */
class main_listener implements EventSubscriberInterface
{
    const OptionSeparator = "-";

    /** @var \phpbb\template\template */
    protected $template;

    /** @var \phpbb\db\driver\driver_interface */
    protected $db;

    /**
     * Constructor
     *
     * @param \phpbb\db\driver\driver_interface        	$db             dbal object
     * @param \phpbb\template\template    $template           Template object
     * @return \davidiq\MultiGroupedPolls\event\listener
     * @access public
     */
    public function __construct(\phpbb\db\driver\driver_interface $db, \phpbb\template\template $template)
    {
        $this->db = $db;
        $this->template = $template;
    }

	static public function getSubscribedEvents()
	{
		return array(
			'core.viewtopic_modify_poll_template_data'	=> 'viewtopic_modify_poll_template_data',
		);
	}

	/**
	 * Modifies the poll data when applicable
	 *
	 * @param \phpbb\event\data	$event	Event object
	 */
	public function viewtopic_modify_poll_template_data($event)
	{
        // Check the poll options to see if it is a multi-grouped poll
        $poll_options_template_data = $event['poll_options_template_data'];
        $is_multigrouped_poll = false;

        if (count($poll_options_template_data) > 2)
        {
            $poll_option = $poll_options_template_data[0];
            // First option should NOT have the separator
            if (strpos($poll_option['POLL_OPTION_CAPTION'], self::OptionSeparator) === false)
            {
                // Check if the next one does
                $is_multigrouped_poll = strpos($poll_options_template_data[1]['POLL_OPTION_CAPTION'], self::OptionSeparator) === 0;
            }
        }

        if ($is_multigrouped_poll)
        {
            $group = 0;
            $poll_most_index = 0;
            $poll_most_list = array();
            $poll_totals = array();
            $poll_option_group_heading = '';
            $poll_group_option_count = array();
            for($i = 0; $i < count($poll_options_template_data); $i++)
            {
                $poll_option = $poll_options_template_data[$i];
                $poll_option['POLL_OPTION_GROUP_HEADING'] = '';
                $poll_option['POLL_OPTION_LAST'] = false;
                if (strpos($poll_option['POLL_OPTION_CAPTION'], self::OptionSeparator) === false)
                {
                    $poll_most_index = 0;
                    $group++;
                    $poll_option_group_heading = $poll_option['POLL_OPTION_CAPTION'];
                    $poll_totals[$group] = 0;
                    $poll_group_option_count[$group] = 0;
                    if ($i > 0)
                    {
                        $poll_options_template_data[$i - 1]['POLL_OPTION_LAST'] = true;
                    }
                }
                elseif (strlen($poll_option['POLL_OPTION_CAPTION']) > 1)
                {
                    $poll_group_option_count[$group] += 1;
                    $poll_option['POLL_OPTION_GROUP_HEADING'] = $poll_option_group_heading;
                    // Remove the separator
                    $poll_option['POLL_OPTION_CAPTION'] = substr($poll_option['POLL_OPTION_CAPTION'], 1);
                    $poll_option['POLL_OPTION_MOST_VOTES'] = false;

                    // Find out if this is the highest result
                    if ($poll_option['POLL_OPTION_RESULT'] > 0)
                    {
                        if ($poll_most_index == 0 || (int)$poll_option['POLL_OPTION_RESULT'] > (int)$poll_options_template_data[$poll_most_index]['POLL_OPTION_RESULT'])
                        {
                            if ($poll_most_index > 0)
                            {
                                $poll_options_template_data[$poll_most_index]['POLL_OPTION_MOST_VOTES'] = false;
                            }
                            $poll_most_list[$group] = (int)$poll_option['POLL_OPTION_RESULT'];
                            $poll_most_index = $i;
                            $poll_option['POLL_OPTION_MOST_VOTES'] = true;
                        }
                    }

                    $poll_totals[$group] += $poll_option['POLL_OPTION_RESULT'];
                }

                $poll_option['POLL_OPTION_GROUP'] = $group;
                $poll_options_template_data[$i] = $poll_option;
            }

            // Last row has to be changed out here
            $poll_options_template_data[$i - 1]['POLL_OPTION_LAST'] = true;

            // Let's get the poll data now
            $sql = 'SELECT v.*, u.*
                    FROM ' . POLL_VOTES_TABLE . ' v
                    JOIN ' . USERS_TABLE . ' u ON u.user_id = v.vote_user_id
                    WHERE v.topic_id = ' . (int)$event['topic_data']['topic_id'];

            $voter_data = array();
            $result = $this->db->sql_query($sql);
            while ($row = $this->db->sql_fetchrow($result))
            {
                $row['username_full'] = get_username_string('full', $row['user_id'], $row['username'], $row['user_colour'], $row['username']);
                $voter_data[] = $row;
            }
            $this->db->sql_freeresult($result);

            $poll_results = array();

            // We now re-calculate the percentages
            for ($i = 0; $i < count($poll_options_template_data); $i++)
            {
                $poll_option = $poll_options_template_data[$i];
                $voter_list = '';

                // Check the voter data
                foreach ($voter_data as $voter)
                {
                    if ($voter['poll_option_id'] == $poll_option['POLL_OPTION_ID'])
                    {
                        $voter_list .= ($voter_list != '' ? ', ' : '') . $voter['username_full'];
                    }
                }

                if ($poll_option['POLL_OPTION_GROUP_HEADING'] !== '')
                {
                    $group = $poll_option['POLL_OPTION_GROUP'];
                    $group_option_count = $poll_group_option_count[$group];
                    $poll_total = $poll_totals[$group];
                    $option_pct = ($poll_total > 0) ? $poll_option['POLL_OPTION_RESULT'] / $poll_total : 0;
                    $option_pct_txt = sprintf("%.1d%%", round($option_pct * 100));
                    $poll_most = isset($poll_most_list[$group]) ? $poll_most_list[$group] : 0;
                    $option_pct_rel = ($poll_most > 0) ? $poll_option['POLL_OPTION_RESULT'] / $poll_most : 0;
                    $option_pct_rel_txt = sprintf("%.1d%%", round($option_pct_rel * 100));

                    $poll_option['POLL_OPTION_PERCENT'] = $option_pct_txt;
                    $poll_option['POLL_OPTION_PERCENT_REL'] = $option_pct_rel_txt;
                    $poll_option['POLL_OPTION_PCT'] = round($option_pct * 100);
                    $poll_option['POLL_OPTION_WIDTH'] = round($option_pct * 250);
                    $poll_option['TOTAL_VOTES'] = $poll_total;
                    // We're leaving 20% width to the first column
                    $poll_option['POLL_OPTION_COL_WIDTH'] = round(80 / $group_option_count);
                }

                $poll_options_template_data[$i] = $poll_option;

                $this->template->assign_block_vars('poll_options_data', $poll_option);

                if ($poll_option['POLL_OPTION_GROUP_HEADING'] !== '')
                {
                    $poll_results[] = array(
                        'POLL_OPTION_VOTER_LIST'    => $voter_list,
                        'POLL_OPTION_GROUP'         => $group,
                    );
                }

                if ($poll_option['POLL_OPTION_LAST'])
                {
                    $this->template->assign_block_vars_array('poll_options_data.results', $poll_results);
                    $poll_results = array();
                }
            }

            $event['poll_options_template_data'] = $poll_options_template_data;

            // We don't need the poll to show. We will show it ourselves in the multi-grouped format
            $poll_template_data = $event['poll_template_data'];
            $poll_template_data['S_HAS_POLL'] = false;
            $poll_template_data['S_HAS_MULTIGROUPED_POLL'] = true;
            $event['poll_template_data'] = $poll_template_data;
        }
	}
}
