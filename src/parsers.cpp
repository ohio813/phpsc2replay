#include "parsers.h"
#include <boost/spirit/include/phoenix.hpp>

using namespace boost::spirit::qi;
using namespace boost::phoenix;

namespace
{
    template<class OutIter>
    OutIter write_escaped(const unsigned char* begin, const unsigned char* end, OutIter out) {
        *out++ = '"';
        for (const unsigned char* i = begin; i != end; ++i) {
            unsigned char c = *i;
            if (' ' <= c and c <= '~' and c != '\\' and c != '"') {
                *out++ = (char)c;
            }
            else {
                *out++ = '\\';
                switch(c) {
                case '"':  *out++ = '"';  break;
                case '\\': *out++ = '\\'; break;
                case '\t': *out++ = 't';  break;
                case '\r': *out++ = 'r';  break;
                case '\n': *out++ = 'n';  break;
                default:
                    char const* const hexdig = "0123456789ABCDEF";
                    *out++ = 'x';
                    *out++ = hexdig[c >> 4];
                    *out++ = hexdig[c & 0xF];
                }
            }
        }
        *out++ = '"';
        return out;
    }
    
    template <typename Context>
    void errorhandler(boost::fusion::vector<const unsigned char*, const unsigned char*, 
                                            const unsigned char*, const info&> params, 
                      Context, error_handler_result)
    {
        std::cerr << "Error! Expecting " << at_c<3>(params) << " here: ";
        write_escaped(at_c<0>(params), at_c<2>(params), std::ostream_iterator<char>(std::cerr));
        std::cerr << " >>>>><<<<< ";
        write_escaped(at_c<2>(params), at_c<1>(params), std::ostream_iterator<char>(std::cerr));
        std::cerr << std::endl;
    }
}

namespace sc2replay { namespace parsers {

        string_rule_type string;
        value_rule_type value;
        kv_rule_type kv;
        player_rule_type player;
        players_rule_type players;


        Initializer::Initializer()
        {
            string %= 
                omit[byte_[_a = _1/2]] > repeat(_a)[byte_];

            value = 
                (&byte_[if_(0<(_1 & 0xc0))[_pass=false]] >> byte_[_val = static_cast_<int>(_1)]) |
                little_word[_val = static_cast_<int>(_1) >> 2];

            kv %= 
                word >> value;

            player = 
                omit[byte_(0x5) > byte_(0x12) > byte_(0x0) > byte_(0x2)] >>
                string /*shortname*/ >> omit[byte_(0x2) > byte_(0x5) > byte_(0x8) > kv > repeat(6)[byte_] >
                                             repeat(2)[kv] > byte_ > byte_(0x4) > byte_(0x2)] >>
                string /*race*/ >> omit[byte_(0x6) > byte_(0x5) > byte_(0x8)] >>
                repeat(9)[kv];

            players %=
                omit[byte_[_a = _1/2]] > repeat(_a)[player];

            player.name("player");
            kv.name("KeyValue");
            value.name("value");
            string.name("string");

            on_error<fail>(player, ::errorhandler<player_rule_type::context_type>);
         
            //debug(string);
            //debug(player);
            //debug(players);

        }

        static Initializer __initializer;
    }
}

// Local Variables:
// mode:c++
// c-file-style: "stroustrup"
// end:

