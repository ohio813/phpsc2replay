#ifndef SC2REPLAY_PARSERS_H
#define SC2REPLAY_PARSERS_H

#include "types.h"
#include "player.h"

#include <boost/spirit/include/qi.hpp>
#include <boost/fusion/adapted/std_pair.hpp>
#include <boost/fusion/include/std_pair.hpp>

namespace sc2replay { namespace parsers {

        /// ugly hack. TODO: refactor
        struct Initializer
        {
            Initializer();
        };
        
        typedef boost::spirit::qi::rule<const uint8_t*, 
                                        boost::spirit::qi::locals<int>, 
                                        std::string() > string_rule_type;
    
        typedef boost::spirit::qi::rule<const uint8_t*, int()> value_rule_type;
    
        typedef boost::spirit::qi::rule<const uint8_t*, 
                                        std::pair<sc2replay::uint16_t, int>()> kv_rule_type;
        
        typedef boost::spirit::qi::rule<const uint8_t*, 
                                        sc2replay::Player()> player_rule_type;

        typedef boost::spirit::qi::rule<const uint8_t*,
                                        boost::spirit::qi::locals<int>,
                                        sc2replay::Players()> players_rule_type;
    
        extern string_rule_type string;
        extern value_rule_type value;
        extern kv_rule_type kv;
        extern player_rule_type player;
        extern players_rule_type players;

  
  } 
}

#endif

// Local Variables:
// mode:c++
// c-file-style: "stroustrup"
// end:

