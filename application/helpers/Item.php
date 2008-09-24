<?php 
/**
 * @version $Id$
 * @copyright Center for History and New Media, 2007-2008
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package OmekaThemes
 **/

/**
 * Helper used to retrieve metadata for an item.
 *
 * @see item()
 * @package Omeka
 * @author CHNM
 * @copyright Center for History and New Media, 2007-2008
 **/
class Omeka_View_Helper_Item
{
    protected $_item;
    
    /**
     * Retrieve metadata for a specific field (henceforth known as 'element') for
     * an item.  The simplest form of this function will retrieve a single text 
     * value for a given field, e.g. item('Title') will return a string corresponding
     * to the first available title.  There are a number of options that can be
     * passed via an array as the second argument.
     * 
     * @param Item Database record representing the item to retrieve the field 
     * data from.
     * @param string Field name to retrieve, which can be the name of an element 
     *     or a selected field name related to items.
     * @param mixed Options for formatting the metadata for display.
     * Default options: 
     *  'delimiter' => return the entire set of metadata as a string, where each 
     *      entry is separated by the string delimiter.
     *  'index' => return the metadata entry at the specific index (starting)
     *      from 0. 
     *  'no_filter' => return the set of metadata without running any of the 
     *      filters.
     *  'snippet' => trim the length of each piece of text to the given length
     *      (integer).
     *  'element_set' => retrieve the element text for an element that belongs 
     *      to a specific set.
     *  'all' => if set to true, this will retrieve an array containing all values
     *      for a single element rather than a specific value.
     *
     * @return string|array|null Null if field does not exist for item. Array
     * if certain options are passed.  String otherwise.
     **/
    public function item($item, $field, $options = array())
    {
        $this->_item = $item;
        
        //Convert the shortcuts for the options into a proper array
        $options = $this->_getOptions($options);
        
        // Retrieve the ElementText records (or other values, like strings,
        // integers, booleans) that correspond to all the element text for the
        // given field.
        $text = $this->getElementText($field, $options);
        
        // Apply any plugin filters to the text prior to escaping it to valid 
        // HTML.
        if (!isset($options['no_filter'])) {
            $text = $this->_filterElementText($text, $field, $options);
        }
        
        // Apply the 'snippet' option before escaping the HTML. If applied after
        // escaping the HTML, this may result in invalid markup.
        if ($snippetLength = (int) @$options['snippet']) {
            $text = $this->_formatSubstring($text, $snippetLength);
        }
                
        // Escape the non-HTML text if necessary.
        $text = $this->_escapeForHtml($text, $options);
        
        // Extract the text from the records into an array.
        // This has to happen after escaping the HTML because the 'html' flag is
        // located within the ElementText record.
        if (is_array($text) && reset($text) instanceof ElementText) {
           $text = $this->_extractTextFromRecords($text); 
        }
        
        // Apply additional formatting options on that array, including 
        // 'delimiter' and 'index'.
        
        // Return the join'd text
        if (isset($options['delimiter'])) {
            return join((string) $options['delimiter'], (array) $text);
        }
        
        // Return the text at that index (suppress errors)
        if (isset($options['index'])) {
            return @$text[$options['index']];
        }
        
        // If the 'all' option is set, return the entire array of escaped data
        if (isset($options['all'])) {
            return $text;
        } elseif (isset($options['index'])) {
            // Return the value at a specific index.
            return @$text[$options['index']];
        } 
        
        // Return the first entry in the array or the whole thing if it's a string.
        return is_array($text) ? reset($text) : $text;
    }
    
    protected function _formatSubstring($texts, $length)
    {
        // Integers get no formatting
        if (is_int($texts)) {
            return $texts;
        } else if (is_string($texts)) {
            return snippet($texts, 0, $length);
        } else if (is_array($texts)) {
            foreach ($texts as $textRecord) {
                $textRecord->setText(snippet($textRecord->getText(), 0, $length));
            }   
            return $texts;         
        }
        
        throw new Exception('Cannot retrieve a text snippet for a data type that is a '. gettype($texts));
    }
    
    protected function _extractTextFromRecords($text)
    {
        $extracted = array();
        foreach ($text as $key => $record) {
            $extracted[$key] = $record->getText();
        }
        return $extracted;
    }
    
    /**
     *  This applies all filters defined for the 'html_escape' filter. This will
     *  only be applied to string values or element text records that are not
     *  marked as HTML. If they are marked as HTML, then there should be no
     *  escaping because the values are already stored in the database as fully
     *  valid HTML markup. Any errors resulting from displaying that HTML is the
     *  responsibility of the administrator to fix.
     * 
     * @param string|array
     * @return string|array
     **/
    protected function _escapeForHtml($texts, array $options)
    {   
        // The assumption here is that all string values (item type name,
        // collection name, etc.) will need to be escaped.
        if (is_string($texts)) {
            return apply_filters('html_escape', $texts);
        } else if (is_array($texts)) {
            foreach ($texts as $record) {
                 if (!$record->isHtml()) {
                     $record->setText(apply_filters('html_escape', $record->getText()));
                 }
             }
             return $texts;
        } else {
            // Just return the text as it is if it is neither a string nor an 
            // array.
            return $texts;
        }
    }
    
    /**
     * Options can sometimes be an integer or a string instead of an array,
     * which functions as a handy shortcut for theme writers.  This converts
     * the short form of the options into its proper array form.
     * 
     * @param mixed
     * @return array
     **/
    protected function _getOptions($options)
    {
        if (is_integer($options)) {
            return array('index' => $options);
        } else if (is_string($options)) {
            return array('delimiter' => $options);
        }
        
        return (array) $options;
    }
    
    /**
     * Apply filters a set of element text.
     * 
     * @todo
     * @param array Set of element text.
     * values.
     * @return array Same structure but run through filters.
     **/
    protected function _filterElementText($text, $field)
    {
        // Build the name of the filter to use. This will end up looking like: 
        // array('Display', 'Item', 'Title', 'Dublin Core') or something similar.
        $filterName = array('Display', 'Item', $field);
        if (isset($options['element_set'])) {
            $filterName[] = (string) $options['element_set'];
        }
        
        if (is_array($text)) {
            
            // What to do if there is no text to filter?  For now, filter an 
            // empty string.
            if (empty($text)) {
                $text[] = new ElementText;
            }
            
            // This really needs to be an instance of ElementText for the following to work.
            if (!(reset($text) instanceof ElementText)) {
                throw new Exception('AAAAAAAAAAAAAAAAAAAH');
            }
            
            // Apply the filters individually to each text record.
            foreach ($text as $record) {
                // This filter receives the Item record as well as the 
                // ElementText record
                $record->setText(apply_filters($filterName, $record->getText(), $item, $record));
            }
        } else {
            $text = apply_filters($filterName, $text, $item, $record);
        }     
        
        return $text;
    }
    
    /**
     * List of fields besides elements that an item can display.
     * 
     * @param string
     * @return boolean
     **/
    public function hasOtherField($field)
    {
        return in_array(strtolower($field),
            array('id', 
                  'featured', 
                  'public', 
                  'item type name', 
                  'date added', 
                  'collection name'));
    }
    
    /**
     * Retrieve the value of any field for an item that does not correspond to
     * an Element record.  Examples include the database ID of the item, the
     * name of the item type, the name of the collection, etc.
     * 
     * @param string
     * @param Item
     * @return mixed
     **/
    public function getOtherField($field, $item)
    {
        switch (strtolower($field)) {
            case 'id':
                return $item->id;
                break;
            case 'item type name':
                return $item->Type->name;
                break;
            case 'date added':
                return $item->added;
                break;
            case 'collection name':
                return $item->Collection->name;
                break;
            case 'featured':
                return $item->featured;
                break;
            case 'public':
                return $item->public;
                break;
            default:
                # code...
                break;
        }
    }
    
    /**
     * Retrieve the set of element text for the item.
     * 
     * @param string
     * @return array|string
     **/
    public function getElementText($field, array $options)
    {
        $item = $this->_item;
        
        // Any built-in fields or special naming schemes
        if ($this->hasOtherField($field)) {
            return $this->getOtherField($field, $item);
        }        
        
        $elementName = $field;
        $elementSetName = @$options['element_set'];
        
        $elementTexts = $item->getElementTextsByElementNameAndSetName($elementName, $elementSetName);
        
        // Lock the records so that they can't be accidentally saved back to the
        // database, since we are modifying their values directly at this point.
        foreach ($elementTexts as $record) {
            $record->lock();
        }
        
        return $elementTexts;
    }
}
